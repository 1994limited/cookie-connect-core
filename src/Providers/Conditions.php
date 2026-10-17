<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

/**
 * The small condition language provider files use in `when`:
 *
 *     autoBlocking          the value is truthy
 *     !autoBlocking         the value is falsy
 *     cdn=eu                the value equals "eu"
 *     idType!=ruleset       the value doesn't equal "ruleset"
 *     tcf&cdn=eu            both
 *
 * Names are field handles, or the context values host, lang and mode.
 */
final class Conditions
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function matches(?string $expression, array $values): bool
    {
        if ($expression === null || trim($expression) === '') {
            return true;
        }

        foreach (explode('&', $expression) as $part) {
            if (! self::part(trim($part), $values)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A value that may depend on settings: a plain value, a `{ value, when }`
     * pair (null when it doesn't apply) or a list of them, where the first
     * that applies wins.
     *
     * @param  array<string, mixed>  $values
     */
    public static function resolve(mixed $spec, array $values): mixed
    {
        if (is_array($spec) && array_is_list($spec) && $spec !== [] && is_array($spec[0]) && array_key_exists('value', $spec[0])) {
            foreach ($spec as $option) {
                if (self::matches($option['when'] ?? null, $values)) {
                    return $option['value'];
                }
            }

            return null;
        }

        if (is_array($spec) && array_key_exists('value', $spec) && count(array_diff(array_keys($spec), ['value', 'when'])) === 0) {
            return self::matches($spec['when'] ?? null, $values) ? $spec['value'] : null;
        }

        return $spec;
    }

    /**
     * The names an expression reads, for checking provider files.
     *
     * @return list<string>
     */
    public static function names(string $expression): array
    {
        $names = [];

        foreach (explode('&', $expression) as $part) {
            $part = ltrim(trim($part), '!');
            $names[] = trim(preg_split('/!?=/', $part, 2)[0] ?? $part);
        }

        return array_values(array_filter($names, fn ($name) => $name !== ''));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function part(string $part, array $values): bool
    {
        if (preg_match('/^([A-Za-z0-9_]+)\s*(!=|=)\s*(.*)$/', $part, $m)) {
            $equal = (string) self::scalar($values[$m[1]] ?? null) === trim($m[3]);

            return $m[2] === '=' ? $equal : ! $equal;
        }

        if (str_starts_with($part, '!')) {
            return ! self::truthy($values[substr($part, 1)] ?? null);
        }

        return self::truthy($values[$part] ?? null);
    }

    private static function truthy(mixed $value): bool
    {
        return ! in_array($value, [null, false, '', 0, '0', 'false', []], true);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === true => '1',
            $value === false, $value === null => '',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
