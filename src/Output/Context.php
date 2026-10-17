<?php

namespace NineteenNinetyFour\CookieConnect\Core\Output;

/**
 * What changes per request rather than per site: the site's host and
 * language, and the CSP nonce if the site uses one.
 */
final class Context
{
    public readonly string $host;

    public readonly string $lang;

    public function __construct(
        string $host = '',
        string $lang = '',
        public readonly ?string $nonce = null,
    ) {
        $host = strtolower(trim($host));
        $host = (string) (parse_url(str_contains($host, '//') ? $host : '//'.$host, PHP_URL_HOST) ?: $host);
        $this->host = (string) preg_replace('/^www\./', '', $host);
        $this->lang = strtolower(substr((string) preg_replace('/[^A-Za-z].*$/', '', trim($lang)), 0, 2));
    }
}
