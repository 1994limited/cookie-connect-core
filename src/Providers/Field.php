<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

/**
 * One setting a provider needs, such as Cookiebot's Domain Group ID.
 */
final class Field
{
    public const TYPES = ['text', 'select', 'toggle', 'code'];

    /**
     * @param  array<string, string>  $options  value => label, for selects
     */
    public function __construct(
        public readonly string $handle,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly bool $required = false,
        public readonly ?string $instructions = null,
        public readonly ?string $example = null,
        public readonly ?string $pattern = null,
        public readonly ?string $extract = null,
        public readonly ?string $extractFrom = null,
        public readonly bool $lowercase = false,
        public readonly array $options = [],
        public readonly mixed $default = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            handle: (string) $data['handle'],
            label: (string) $data['label'],
            type: (string) ($data['type'] ?? 'text'),
            required: (bool) ($data['required'] ?? false),
            instructions: isset($data['instructions']) ? (string) $data['instructions'] : null,
            example: isset($data['example']) ? (string) $data['example'] : null,
            pattern: isset($data['pattern']) ? (string) $data['pattern'] : null,
            extract: isset($data['extract']) ? (string) $data['extract'] : null,
            extractFrom: isset($data['extract_from']) ? (string) $data['extract_from'] : null,
            lowercase: (bool) ($data['lowercase'] ?? false),
            options: array_map('strval', (array) ($data['options'] ?? [])),
            default: $data['default'] ?? null,
        );
    }

    /**
     * Turns what an editor typed or pasted into the value to store: trimmed,
     * the ID taken out of a pasted snippet, lower-cased where the provider's
     * IDs are. A field with `extract_from` also looks in that field's input,
     * so one pasted snippet can fill two fields.
     *
     * @param  array<string, mixed>  $input  every field's raw input
     */
    public function normalise(mixed $value, array $input = []): mixed
    {
        if ($this->type === 'toggle') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($this->type === 'code') {
            return $value;
        }

        if ($this->extract !== null) {
            $source = $value === '' && $this->extractFrom !== null
                ? trim((string) ($input[$this->extractFrom] ?? ''))
                : $value;

            if ($source !== '' && ($this->pattern === null || ! preg_match($this->regex($this->pattern), $source))
                && preg_match($this->regex($this->extract), $source, $m)) {
                $value = $m[1];
            }
        }

        return $this->lowercase ? strtolower($value) : $value;
    }

    /**
     * What's wrong with a normalised value, in words an editor understands,
     * or null when it's fine.
     */
    public function problem(mixed $value): ?string
    {
        if ($this->type === 'toggle') {
            return null;
        }

        $value = is_scalar($value) ? (string) $value : '';

        if ($value === '') {
            return $this->required ? sprintf('%s is required.', $this->label) : null;
        }

        if ($this->type === 'select' && $this->options !== [] && ! array_key_exists($value, $this->options)) {
            return sprintf('%s must be one of: %s.', $this->label, implode(', ', array_keys($this->options)));
        }

        if ($this->pattern !== null && ! preg_match($this->regex($this->pattern), $value)) {
            return $this->example !== null
                ? sprintf('%s doesn’t look right. It should look like %s.', $this->label, $this->example)
                : sprintf('%s doesn’t look right.', $this->label);
        }

        return null;
    }

    private function regex(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~';
    }
}
