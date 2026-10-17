<?php

namespace NineteenNinetyFour\CookieConnect\Core\Output;

/**
 * The browser script every page gets: it reads consent from whichever
 * platform the site uses, runs scripts blocked with Cookie Connect's own
 * markup, sends Consent Mode updates where the platform doesn't, and gives
 * site code one API (window.CookieConnect).
 */
final class Runtime
{
    public const VERSION = '1.0.0';

    private static ?string $source = null;

    public static function path(): string
    {
        $dir = dirname(__DIR__, 2).'/resources/runtime';

        return is_file($dir.'/cookie-connect.min.js') ? $dir.'/cookie-connect.min.js' : $dir.'/cookie-connect.js';
    }

    public static function source(): string
    {
        return self::$source ??= trim((string) file_get_contents(self::path()));
    }
}
