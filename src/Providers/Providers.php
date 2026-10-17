<?php

namespace NineteenNinetyFour\CookieConnect\Core\Providers;

use Symfony\Component\Yaml\Yaml;

/**
 * Every consent platform Cookie Connect knows, in the order the settings
 * list them. Sites and add-ons can add their own with register().
 */
final class Providers
{
    /** The order of the settings list; any others follow, by name. Custom is always last. */
    public const ORDER = ['cookiebot', 'usercentrics', 'onetrust', 'cookieyes', 'iubenda', 'termly', 'cookiefirst', 'cookie-script'];

    /** @var array<string, Provider> */
    private array $providers = [];

    private static ?self $default = null;

    /**
     * The providers that come with Cookie Connect.
     */
    public static function default(): self
    {
        return self::$default ??= self::fromDirectory(dirname(__DIR__, 2).'/resources/providers');
    }

    public static function fromDirectory(string $directory): self
    {
        $providers = new self;

        foreach (glob(rtrim($directory, '/').'/*.yaml') ?: [] as $file) {
            $providers->register(self::load($file));
        }

        return $providers;
    }

    public static function load(string $file): Provider
    {
        $data = Yaml::parseFile($file);

        if (! is_array($data)) {
            throw InvalidProvider::because($file, ['It isn\'t a YAML map.']);
        }

        $problems = ProviderSchema::problems($data);

        if ($problems !== []) {
            throw InvalidProvider::because($file, $problems);
        }

        return Provider::fromArray($data);
    }

    public function register(Provider $provider): self
    {
        $this->providers[$provider->handle] = $provider;

        return $this;
    }

    public function get(?string $handle): ?Provider
    {
        return $handle === null ? null : ($this->providers[$handle] ?? null);
    }

    /**
     * @return list<Provider>
     */
    public function all(): array
    {
        $providers = array_values($this->providers);

        usort($providers, function (Provider $a, Provider $b) {
            $rank = fn (Provider $p) => $p->handle === 'custom' ? PHP_INT_MAX : (array_search($p->handle, self::ORDER, true) === false ? 1000 : (int) array_search($p->handle, self::ORDER, true));

            return [$rank($a), $a->name] <=> [$rank($b), $b->name];
        });

        return $providers;
    }

    /**
     * Handle => name, for a settings select.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $provider) {
            $options[$provider->handle] = $provider->name;
        }

        return $options;
    }
}
