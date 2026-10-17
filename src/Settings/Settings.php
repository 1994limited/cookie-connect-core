<?php

namespace NineteenNinetyFour\CookieConnect\Core\Settings;

/**
 * Everything Cookie Connect needs to know about one site. The Craft plugin
 * and the Statamic addon build it from their own settings and config files.
 */
final class Settings
{
    /** When the site is off (e.g. on staging): keep consent-gated content blocked, run it as if accepted, or leave it out. */
    public const WHEN_OFF = ['block', 'run', 'omit'];

    /**
     * @param  array<string, mixed>  $values  the provider's field values
     * @param  array<string, string|null>  $categoryMap  our category => the provider's id, overriding its defaults
     * @param  list<Script>  $scripts
     */
    public function __construct(
        public readonly ?string $provider = null,
        public readonly array $values = [],
        public readonly array $categoryMap = [],
        public readonly GoogleTags $google = new GoogleTags,
        public readonly array $scripts = [],
        public readonly bool $active = true,
        public readonly string $whenOff = 'block',
        public readonly bool $debug = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $provider = trim((string) ($data['provider'] ?? ''));

        return new self(
            provider: $provider === '' || $provider === 'none' ? null : $provider,
            values: is_array($data['values'] ?? null) ? $data['values'] : [],
            categoryMap: array_map(
                fn ($value) => $value === null || trim((string) $value) === '' ? null : trim((string) $value),
                is_array($data['categoryMap'] ?? null) ? $data['categoryMap'] : [],
            ),
            google: GoogleTags::fromArray(is_array($data['google'] ?? null) ? $data['google'] : []),
            scripts: array_values(array_map(
                fn ($script) => Script::fromArray((array) $script),
                array_filter(is_array($data['scripts'] ?? null) ? $data['scripts'] : [], 'is_array'),
            )),
            active: filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            whenOff: in_array($data['whenOff'] ?? null, self::WHEN_OFF, true) ? $data['whenOff'] : 'block',
            debug: filter_var($data['debug'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'values' => $this->values,
            'categoryMap' => $this->categoryMap,
            'google' => $this->google->toArray(),
            'scripts' => array_map(fn (Script $script) => $script->toArray(), $this->scripts),
            'active' => $this->active,
            'whenOff' => $this->whenOff,
            'debug' => $this->debug,
        ];
    }

    /**
     * Settings data without the dotted paths given ("provider",
     * "values.cbid", "google.gtmId"), e.g. those fixed in a config file,
     * so they aren't saved alongside it.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public static function without(array $data, array $paths): array
    {
        // "google" alongside "google.gtmId" means the group was named on the way to the field: drop the field only.
        $paths = array_filter($paths, function (string $path) use ($paths) {
            foreach ($paths as $other) {
                if (str_starts_with($other, $path.'.')) {
                    return false;
                }
            }

            return true;
        });

        foreach ($paths as $path) {
            $keys = explode('.', $path);
            $last = array_pop($keys);
            $node = &$data;

            foreach ($keys as $key) {
                if (! isset($node[$key]) || ! is_array($node[$key])) {
                    unset($node);

                    continue 2;
                }

                $node = &$node[$key];
            }

            unset($node[$last]);
            unset($node);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_replace($this->toArray(), $changes));
    }
}
