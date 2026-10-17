<?php

namespace NineteenNinetyFour\CookieConnect\Core\Settings;

use NineteenNinetyFour\CookieConnect\Core\Providers\Providers;

/**
 * Cleans up what an editor entered (an ID taken out of a pasted snippet,
 * GTM-… upper-cased) and says what's still wrong, field by field.
 */
final class Validator
{
    public function __construct(private readonly Providers $providers) {}

    /**
     * The settings as they should be saved.
     */
    public function normalise(Settings $settings): Settings
    {
        $provider = $this->providers->get($settings->provider);
        $values = $settings->values;

        if ($provider !== null) {
            $normalised = [];

            foreach ($provider->fields as $field) {
                // Defaults that depend on the site ({host}) are worked out when rendering, not stored.
                $default = is_string($field->default) && str_contains($field->default, '{') ? '' : $field->default;
                $normalised[$field->handle] = $field->normalise($values[$field->handle] ?? $default, $values);
            }

            $values = $normalised;
        }

        $google = $settings->google->toArray();
        $google['gtmId'] = $google['gtmId'] === null ? null : GoogleTags::extractGtm($google['gtmId']);
        $google['ga4Id'] = $google['ga4Id'] === null ? null : GoogleTags::extractGa4($google['ga4Id']);

        return $settings->with(['values' => $values, 'google' => $google]);
    }

    /**
     * Problems keyed by where they are: "provider", "values.cbid",
     * "google.gtmId", "scripts.2.code".
     *
     * @return array<string, string>
     */
    public function problems(Settings $settings): array
    {
        $problems = [];

        if ($settings->provider !== null) {
            $provider = $this->providers->get($settings->provider);

            if ($provider === null) {
                $problems['provider'] = sprintf('Cookie Connect doesn’t know a consent platform called "%s".', $settings->provider);
            } else {
                $values = $provider->values($settings->values);

                foreach ($provider->fields as $field) {
                    $default = $field->default;
                    $usesDefault = ($settings->values[$field->handle] ?? '') === '' && is_string($default) && str_contains($default, '{');

                    if (! $usesDefault && ($problem = $field->problem($values[$field->handle] ?? null))) {
                        $problems['values.'.$field->handle] = $problem;
                    }
                }
            }
        }

        $google = $settings->google;

        if ($google->gtmId !== null && ! preg_match(GoogleTags::GTM_PATTERN, $google->gtmId)) {
            $problems['google.gtmId'] = 'The Google Tag Manager ID should look like GTM-ABC1234.';
        }

        if ($google->ga4Id !== null && ! preg_match(GoogleTags::GA4_PATTERN, $google->ga4Id)) {
            $problems['google.ga4Id'] = 'The Google Analytics measurement ID should look like G-ABC123XYZ9.';
        }

        foreach ($settings->scripts as $i => $script) {
            if ($script->name === '') {
                $problems["scripts.{$i}.name"] = 'Give the script a name, so you can find it later.';
            }

            if (trim($script->code) === '') {
                $problems["scripts.{$i}.code"] = 'Paste the script’s code.';
            } elseif (! preg_match('/<(script|iframe|img|noscript|link|style|div)\b/i', $script->code)) {
                $problems["scripts.{$i}.code"] = 'Paste the whole code, including its <script> tags.';
            }
        }

        foreach ($settings->categoryMap as $category => $id) {
            if ($id !== null && ! preg_match('/^[A-Za-z0-9_ ,.-]{1,64}$/', $id)) {
                $problems["categoryMap.{$category}"] = 'Use the category’s id as your consent platform shows it.';
            }
        }

        return $problems;
    }
}
