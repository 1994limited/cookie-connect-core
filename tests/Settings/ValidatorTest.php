<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Settings;

use NineteenNinetyFour\CookieConnect\Core\Providers\Providers;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;
use NineteenNinetyFour\CookieConnect\Core\Settings\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private function validator(): Validator
    {
        return new Validator(Providers::default());
    }

    public function test_it_explains_each_problem_where_it_is(): void
    {
        $settings = Settings::fromArray([
            'provider' => 'cookiebot',
            'values' => ['cbid' => 'nope', 'cdn' => 'de'],
            'google' => ['gtmId' => 'GTM-', 'ga4Id' => 'UA-12345-1'],
            'scripts' => [['name' => '', 'code' => 'hj()', 'categories' => ['analytics']]],
            'categoryMap' => ['analytics' => '<b>'],
        ]);

        $this->assertSame([
            'values.cbid',
            'values.cdn',
            'google.gtmId',
            'google.ga4Id',
            'scripts.0.name',
            'scripts.0.code',
            'categoryMap.analytics',
        ], array_keys($this->validator()->problems($settings)));
    }

    public function test_an_unknown_provider_is_a_problem_and_none_is_not(): void
    {
        $this->assertArrayHasKey('provider', $this->validator()->problems(new Settings(provider: 'acme')));
        $this->assertSame([], $this->validator()->problems(Settings::fromArray(['provider' => 'none'])));
    }

    public function test_it_normalises_pasted_google_snippets(): void
    {
        $settings = $this->validator()->normalise(Settings::fromArray([
            'google' => [
                'gtmId' => "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){…})(window,document,'script','dataLayer','GTM-AB12CD3');</script>",
                'ga4Id' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ9"></script>',
            ],
        ]));

        $this->assertSame('GTM-AB12CD3', $settings->google->gtmId);
        $this->assertSame('G-ABC123XYZ9', $settings->google->ga4Id);
    }

    public function test_settings_round_trip_through_arrays(): void
    {
        $data = [
            'provider' => 'onetrust',
            'values' => ['domainScript' => 'x'],
            'categoryMap' => ['analytics' => '2', 'marketing' => null],
            'google' => ['gtmId' => 'GTM-AB12CD3', 'ga4Id' => null, 'consentMode' => true, 'mode' => 'basic', 'regions' => 'eea', 'urlPassthrough' => true, 'adsDataRedaction' => false, 'gtmSetsDefaults' => false, 'waitForUpdate' => 1000],
            'scripts' => [['name' => 'Hotjar', 'categories' => ['analytics'], 'code' => '<script></script>', 'placement' => 'body_end', 'enabled' => false]],
            'active' => false,
            'whenOff' => 'run',
            'debug' => true,
        ];

        $this->assertSame($data, Settings::fromArray($data)->toArray());
    }
}
