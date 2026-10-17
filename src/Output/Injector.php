<?php

namespace NineteenNinetyFour\CookieConnect\Core\Output;

/**
 * Puts Cookie Connect's output into a finished page, so nobody has to edit a
 * template: the head block straight after <head>, the body blocks straight
 * after <body> and before </body>. A block a template already printed (with
 * the head tag or function) isn't added twice.
 */
final class Injector
{
    public const HEAD = '<!-- Cookie Connect -->';

    public const BODY_START = '<!-- Cookie Connect: body start -->';

    public const BODY_END = '<!-- Cookie Connect: body end -->';

    public static function inject(string $page, string $head, string $bodyStart = '', string $bodyEnd = ''): string
    {
        if (! preg_match('/<head(?=[\s>])[^>]*>/i', $page, $m, PREG_OFFSET_CAPTURE)) {
            return $page;
        }

        if ($head !== '' && ! str_contains($page, self::HEAD)) {
            $at = (int) $m[0][1] + strlen($m[0][0]);
            $page = substr($page, 0, $at)."\n".$head.substr($page, $at);
        }

        if ($bodyStart !== '' && ! str_contains($page, self::BODY_START)
            && preg_match('/<body(?=[\s>])[^>]*>/i', $page, $b, PREG_OFFSET_CAPTURE)) {
            $at = (int) $b[0][1] + strlen($b[0][0]);
            $page = substr($page, 0, $at)."\n".$bodyStart.substr($page, $at);
        }

        if ($bodyEnd !== '' && ! str_contains($page, self::BODY_END) && ($at = strripos($page, '</body>')) !== false) {
            $page = substr($page, 0, $at).$bodyEnd."\n".substr($page, $at);
        }

        return $page;
    }

    /**
     * Whether a response looks like a whole HTML page worth injecting into.
     */
    public static function isPage(string $body, ?string $contentType = null): bool
    {
        if ($contentType !== null && $contentType !== '' && ! str_contains(strtolower($contentType), 'html')) {
            return false;
        }

        return (bool) preg_match('/<head(?=[\s>])/i', $body);
    }
}
