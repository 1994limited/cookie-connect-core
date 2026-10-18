<?php

namespace NineteenNinetyFour\CookieConnect\Core\Html;

/**
 * Writes a tag from plain (unescaped) values.
 */
final class Tag
{
    public const VOID = ['img', 'link', 'meta', 'br', 'hr', 'input', 'source'];

    /**
     * @param  array<string, string|bool|null>  $attributes  true for a bare attribute; false and null are left out
     * @param  string|null  $content  already safe for where it goes
     */
    public static function make(string $name, array $attributes = [], ?string $content = null): string
    {
        $html = '<'.$name;

        foreach ($attributes as $attribute => $value) {
            // Names never come from content, but a stray one mustn't open a hole: only plain attribute names.
            if ($value === null || $value === false || ! preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', (string) $attribute)) {
                continue;
            }

            $html .= $value === true
                ? " {$attribute}"
                : " {$attribute}=\"".htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
        }

        $html .= '>';

        return in_array($name, self::VOID, true) ? $html : $html.($content ?? '').'</'.$name.'>';
    }

    public static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Makes text safe inside an inline script: a "</script" in it can't end
     * the element early.
     */
    public static function script(string $code): string
    {
        return (string) preg_replace('/<(\/script|!--)/i', '<\\\\$1', $code);
    }
}
