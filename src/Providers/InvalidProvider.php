<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

final class InvalidProvider extends \InvalidArgumentException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(string $source, array $problems): self
    {
        return new self("The provider in {$source} has problems:\n- ".implode("\n- ", $problems));
    }
}
