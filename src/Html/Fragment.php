<?php

namespace NineteenNinetyFour\CookieConnect\Core\Html;

/**
 * Finds the scripts, iframes and images in a piece of HTML and lets a
 * callback change them, leaving every other byte as it was. It doesn't
 * parse the whole document (so nothing is "corrected" behind your back):
 * it skips comments and the insides of scripts, styles and textareas, and
 * rewrites only the opening tags it's asked about.
 */
final class Fragment
{
    public const ELEMENTS = ['script', 'iframe', 'img'];

    /**
     * @param  callable(Element): (Element|null)  $change  return null to drop the element
     */
    public static function rewrite(string $html, callable $change): string
    {
        $out = '';
        $length = strlen($html);
        $position = 0;

        while ($position < $length && preg_match('/<!--|<(script|iframe|img|style|textarea)(?=[\s>\/])/i', $html, $m, PREG_OFFSET_CAPTURE, $position)) {
            $start = (int) $m[0][1];
            $out .= substr($html, $position, $start - $position);

            if ($m[0][0] === '<!--') {
                $end = strpos($html, '-->', $start + 4);
                $end = $end === false ? $length : $end + 3;
                $out .= substr($html, $start, $end - $start);
                $position = $end;

                continue;
            }

            $name = strtolower($m[1][0] ?? '');
            $tagEnd = self::tagEnd($html, $start + strlen($m[0][0]));

            if ($tagEnd === null) {
                $out .= substr($html, $start);
                $position = $length;

                break;
            }

            $opening = substr($html, $start, $tagEnd - $start + 1);
            $hasContent = in_array($name, ['script', 'style', 'textarea'], true);
            $content = null;
            $after = $tagEnd + 1;
            $closing = '';

            if ($hasContent) {
                $close = preg_match('/<\/'.$name.'\s*>/i', $html, $c, PREG_OFFSET_CAPTURE, $after) ? (int) $c[0][1] : null;
                $content = $close === null ? substr($html, $after) : substr($html, $after, $close - $after);
                $closing = $close === null ? '' : $c[0][0];
                $after = $close === null ? $length : $close + strlen($c[0][0]);
            }

            if (! in_array($name, self::ELEMENTS, true)) {
                $out .= $opening.$content.$closing;
                $position = $after;

                continue;
            }

            $element = new Element($name, self::attributes($opening, strlen($m[0][0])), $content);
            $changed = $change($element);

            if ($changed !== null) {
                $out .= $changed->openingTag().($hasContent ? ($changed->content ?? '').($closing !== '' ? $closing : '</'.$name.'>') : '');
            }

            $position = $after;
        }

        return $out.substr($html, $position);
    }

    /**
     * @return list<Element>
     */
    public static function find(string $html): array
    {
        $found = [];

        self::rewrite($html, function (Element $element) use (&$found) {
            $found[] = $element;

            return $element;
        });

        return $found;
    }

    /**
     * Where the opening tag that starts before $from ends, minding quoted
     * attribute values that contain ">".
     */
    private static function tagEnd(string $html, int $from): ?int
    {
        $length = strlen($html);
        $quote = null;

        for ($i = $from; $i < $length; $i++) {
            $char = $html[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|true>
     */
    private static function attributes(string $opening, int $skip): array
    {
        $inner = substr($opening, $skip, -1);
        $attributes = [];

        preg_match_all('/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', $inner, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = strtolower($match[1]);

            if (array_key_exists($name, $attributes)) {
                continue;
            }

            if (! isset($match[2]) && ! isset($match[3]) && ! isset($match[4])) {
                $attributes[$name] = true;

                continue;
            }

            $value = ($match[2] ?? '') !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
            $attributes[$name] = str_replace('"', '&quot;', $value);
        }

        return $attributes;
    }
}
