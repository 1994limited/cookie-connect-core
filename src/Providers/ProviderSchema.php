<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

use NineteenNinetyFour\CookieConnect\Core\Category;

/**
 * Checks a provider file before it's used, so a mistake in one shows up as a
 * clear list of problems (in tests and when a site registers its own) rather
 * than as broken markup.
 */
final class ProviderSchema
{
    /** Names conditions and templates may use besides field handles. */
    public const CONTEXT = ['host', 'lang', 'mode', 'categories'];

    public const KINDS = ['script_inline', 'script_src', 'iframe', 'img'];

    /**
     * @param  array<mixed>  $data
     * @return list<string> problems, empty when the file is fine
     */
    public static function problems(array $data): array
    {
        $problems = [];
        $need = fn (string $key) => array_key_exists($key, $data) ? null : "Missing \"{$key}\".";

        foreach (['handle', 'name', 'docs', 'verified', 'fields', 'head', 'categories', 'blocking', 'consent_mode'] as $key) {
            if ($problem = $need($key)) {
                $problems[] = $problem;
            }
        }

        if ($problems !== []) {
            return $problems;
        }

        if (! is_string($data['handle']) || ! preg_match('/^[a-z0-9-]+$/', $data['handle'])) {
            $problems[] = 'The handle must be lower-case letters, digits and hyphens.';
        }

        $verified = is_int($data['verified']) ? date('Y-m-d', $data['verified']) : (string) $data['verified'];

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $verified)) {
            $problems[] = 'verified must be a date (YYYY-MM-DD).';
        }

        if (! is_array($data['docs']) || $data['docs'] === []) {
            $problems[] = 'docs must list at least one URL.';
        }

        $handles = [];

        foreach ((array) $data['fields'] as $i => $field) {
            if (! is_array($field) || ! isset($field['handle'], $field['label'])) {
                $problems[] = "Field {$i} needs a handle and a label.";

                continue;
            }

            $handle = (string) $field['handle'];
            $handles[] = $handle;

            if (in_array($handle, self::CONTEXT, true)) {
                $problems[] = "Field \"{$handle}\" uses a reserved name.";
            }

            if (! in_array($field['type'] ?? 'text', Field::TYPES, true)) {
                $problems[] = "Field \"{$handle}\" has an unknown type.";
            }

            foreach (['pattern', 'extract'] as $key) {
                if (isset($field[$key]) && @preg_match('~'.str_replace('~', '\~', (string) $field[$key]).'~', '') === false) {
                    $problems[] = "Field \"{$handle}\" has an invalid {$key}.";
                }
            }

            if (isset($field['extract']) && ! str_contains((string) $field['extract'], '(')) {
                $problems[] = "Field \"{$handle}\"'s extract needs a capture group.";
            }

            if (($field['type'] ?? 'text') === 'select' && empty($field['options'])) {
                $problems[] = "Field \"{$handle}\" is a select without options.";
            }

            if (isset($field['example'], $field['pattern']) && ! preg_match('~'.str_replace('~', '\~', (string) $field['pattern']).'~', (string) $field['example'])) {
                $problems[] = "Field \"{$handle}\"'s example doesn't match its pattern.";
            }
        }

        $known = array_merge($handles, self::CONTEXT);

        foreach (['head' => $data['head'], 'declaration' => $data['declaration'] ?? []] as $section => $tags) {
            foreach ((array) $tags as $i => $tag) {
                foreach (self::tagProblems($tag, $known) as $problem) {
                    $problems[] = "{$section}[{$i}]: {$problem}";
                }
            }
        }

        foreach (Category::cases() as $category) {
            if (! array_key_exists($category->value, (array) $data['categories'])) {
                $problems[] = "categories needs \"{$category->value}\".";
            }
        }

        foreach ((array) $data['blocking'] as $kind => $rule) {
            if (in_array($kind, ['join', 'multiple'], true)) {
                continue;
            }

            if (! in_array($kind, self::KINDS, true)) {
                $problems[] = "blocking has an unknown kind \"{$kind}\".";

                continue;
            }

            if (! in_array($rule['strategy'] ?? null, ['native', 'managed'], true)) {
                $problems[] = "blocking.{$kind} needs a strategy: native or managed.";
            }

            if (($rule['strategy'] ?? null) === 'native' && empty($rule['attrs'])) {
                $problems[] = "blocking.{$kind} is native but adds no attributes.";
            }
        }

        foreach (['defaults', 'updates'] as $key) {
            foreach (self::outcomes($data['consent_mode'][$key] ?? 'ours') as $value) {
                if (! in_array($value, ['ours', 'cmp'], true)) {
                    $problems[] = "consent_mode.{$key} must be ours or cmp.";
                }
            }
        }

        foreach (self::outcomes($data['google_tags'] ?? 'after_cmp') as $value) {
            if (! in_array($value, ['before_cmp', 'after_cmp'], true)) {
                $problems[] = 'google_tags must be before_cmp or after_cmp.';
            }
        }

        foreach (self::expressions($data) as $expression) {
            foreach (Conditions::names($expression) as $name) {
                if (! in_array($name, $known, true)) {
                    $problems[] = "The condition \"{$expression}\" uses unknown name \"{$name}\".";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * @param  list<string>  $known
     * @return list<string>
     */
    private static function tagProblems(mixed $tag, array $known): array
    {
        if (! is_array($tag)) {
            return ['must be a tag.'];
        }

        if (isset($tag['raw'])) {
            return self::unknownPlaceholders((string) $tag['raw'], $known);
        }

        if (! isset($tag['tag']) || ! preg_match('/^[a-z][a-z0-9-]*$/', (string) $tag['tag'])) {
            return ['needs a tag name.'];
        }

        $problems = [];

        foreach ((array) ($tag['attrs'] ?? []) as $name => $value) {
            foreach (self::strings($value) as $string) {
                array_push($problems, ...self::unknownPlaceholders($string, $known));
            }
        }

        if (isset($tag['content'])) {
            array_push($problems, ...self::unknownPlaceholders((string) $tag['content'], $known));
        }

        return $problems;
    }

    /**
     * @param  list<string>  $known
     * @return list<string>
     */
    private static function unknownPlaceholders(string $template, array $known): array
    {
        $problems = [];

        foreach (Template::names($template) as $name) {
            if (! in_array($name, $known, true)) {
                $problems[] = "uses unknown placeholder {{$name}}.";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (is_array($value)) {
            return array_merge(...array_map(fn ($item) => self::strings($item), array_values($value))) ?: [];
        }

        return [];
    }

    /**
     * @return list<mixed>
     */
    private static function outcomes(mixed $spec): array
    {
        if (is_array($spec) && array_is_list($spec)) {
            return array_map(fn ($option) => is_array($option) ? ($option['value'] ?? null) : $option, $spec);
        }

        return [is_array($spec) ? ($spec['value'] ?? null) : $spec];
    }

    /**
     * Every `when` in the file.
     *
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private static function expressions(array $data): array
    {
        $found = [];

        array_walk_recursive($data, function ($value, $key) use (&$found) {
            if ($key === 'when' && is_string($value)) {
                $found[] = $value;
            }
        });

        return $found;
    }
}
