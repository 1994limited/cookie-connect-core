<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

/**
 * Fills `{name}` placeholders in provider files from settings and context.
 * Values are inserted as they are; escaping is the caller's job, done once
 * for the place the text ends up (attribute, text or script).
 */
final class Template
{
    /**
     * @param  array<string, mixed>  $values
     * @param  callable(string, string): string|null  $format  given the value and its modifier (e.g. "raw")
     */
    public static function fill(string $template, array $values, ?callable $format = null): string
    {
        return (string) preg_replace_callback('/\{([A-Za-z0-9_]+)(?:\|([a-z]+))?\}/', function (array $m) use ($values, $format) {
            $value = $values[$m[1]] ?? '';
            $value = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : '');

            return $format ? $format($value, $m[2] ?? '') : $value;
        }, $template);
    }

    /**
     * The placeholder names in a template.
     *
     * @return list<string>
     */
    public static function names(string $template): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)(?:\|[a-z]+)?\}/', $template, $m);

        return array_values(array_unique($m[1]));
    }
}
