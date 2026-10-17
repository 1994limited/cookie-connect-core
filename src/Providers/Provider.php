<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

use NineteenNinetyFour\CookieConnect\Core\Category;

/**
 * One consent platform, read from its file in resources/providers. Everything
 * that differs between platforms is here as data: the fields, the head tags,
 * how to block a script, who sets Consent Mode defaults, the declaration.
 * See docs/providers.md for the format.
 */
final class Provider
{
    /**
     * @param  list<string>  $docs
     * @param  list<string>  $unverified
     * @param  list<Field>  $fields
     * @param  list<array<string, mixed>>  $head
     * @param  array<string, mixed>  $exempt
     * @param  array<string, string|null>  $categories
     * @param  array<string, mixed>  $blocking
     * @param  array<string, mixed>  $consentMode
     * @param  list<array<string, mixed>>  $declaration
     * @param  array<string, string>  $api
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $handle,
        public readonly string $name,
        public readonly ?string $website,
        public readonly array $docs,
        public readonly string $verified,
        public readonly bool $googlePartner,
        public readonly bool $tcf,
        public readonly array $unverified,
        public readonly array $fields,
        public readonly array $head,
        public readonly array $exempt,
        public readonly array $categories,
        public readonly array $blocking,
        public readonly array $consentMode,
        public readonly mixed $googleTags,
        public readonly ?string $preferencesClass,
        public readonly array $declaration,
        public readonly array $api,
        public readonly array $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  a provider file, already checked by ProviderSchema
     */
    public static function fromArray(array $data): self
    {
        $verified = $data['verified'];

        return new self(
            handle: (string) $data['handle'],
            name: (string) $data['name'],
            website: isset($data['website']) ? (string) $data['website'] : null,
            docs: array_values(array_map('strval', (array) ($data['docs'] ?? []))),
            verified: is_int($verified) ? date('Y-m-d', $verified) : (string) $verified,
            googlePartner: (bool) ($data['google_partner'] ?? false),
            tcf: (bool) ($data['tcf'] ?? false),
            unverified: array_values(array_map('strval', (array) ($data['unverified'] ?? []))),
            fields: array_map(fn (array $field) => Field::fromArray($field), array_values($data['fields'] ?? [])),
            head: array_values($data['head'] ?? []),
            exempt: (array) ($data['exempt'] ?? []),
            categories: array_map(fn ($value) => $value === null ? null : (string) $value, (array) $data['categories']),
            blocking: (array) ($data['blocking'] ?? []),
            consentMode: (array) ($data['consent_mode'] ?? []),
            googleTags: $data['google_tags'] ?? 'after_cmp',
            preferencesClass: isset($data['preferences']['class']) ? (string) $data['preferences']['class'] : null,
            declaration: array_values($data['declaration'] ?? []),
            api: array_map('strval', (array) ($data['api'] ?? [])),
            notes: array_values(array_map('strval', (array) ($data['notes'] ?? []))),
        );
    }

    public function field(string $handle): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->handle === $handle) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Every field's stored value, with defaults filled in (defaults may use
     * the context: `{host}`), plus the context values themselves.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $context  host, lang, mode
     * @return array<string, mixed>
     */
    public function values(array $stored, array $context = []): array
    {
        $values = $context;

        foreach ($this->fields as $field) {
            $value = $stored[$field->handle] ?? null;

            if ($value === null || $value === '') {
                $value = is_string($field->default) ? Template::fill($field->default, $values + $stored) : $field->default;
            }

            $values[$field->handle] = $field->type === 'toggle' ? (bool) $value : ($value ?? '');
        }

        return $values;
    }

    /**
     * The provider's own name for each of our categories, with the site's
     * overrides applied. Null means "no markup needed" (necessary).
     *
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    public function categoryMap(array $overrides = []): array
    {
        $map = [];

        foreach (Category::cases() as $category) {
            $override = $overrides[$category->value] ?? null;
            $map[$category->value] = $override !== null && trim($override) !== ''
                ? trim($override)
                : ($this->categories[$category->value] ?? null);
        }

        return $map;
    }

    /**
     * Who sets Consent Mode defaults ("ours" or "cmp") for these settings.
     *
     * @param  array<string, mixed>  $values
     */
    public function setsDefaults(array $values): bool
    {
        return Conditions::resolve($this->consentMode['defaults'] ?? 'ours', $values) === 'cmp';
    }

    /**
     * Whether the platform sends Consent Mode updates itself. When it
     * doesn't, Cookie Connect's script does.
     *
     * @param  array<string, mixed>  $values
     */
    public function sendsUpdates(array $values): bool
    {
        return Conditions::resolve($this->consentMode['updates'] ?? 'cmp', $values) === 'cmp';
    }

    public function waitForUpdate(): int
    {
        return (int) ($this->consentMode['wait_for_update'] ?? 500);
    }

    /**
     * Whether Google tags go before the platform's script ("before_cmp") or
     * after it.
     *
     * @param  array<string, mixed>  $values
     */
    public function googleTagsFirst(array $values): bool
    {
        return Conditions::resolve($this->googleTags, $values) === 'before_cmp';
    }

    /**
     * Attributes that keep the platform's auto-blocker off Cookie Connect's
     * own scripts, for these settings.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public function exemptAttributes(array $values): array
    {
        $attributes = [];

        foreach ($this->exempt as $name => $spec) {
            $value = Conditions::resolve($spec, $values);

            if (is_scalar($value) && $value !== false && $value !== '') {
                $attributes[(string) $name] = (string) $value;
            }
        }

        return $attributes;
    }

    public function hasDeclaration(): bool
    {
        return $this->declaration !== [];
    }
}
