<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Providers;

use NineteenNinetyFour\CookieConnect\Core\Providers\Field;
use NineteenNinetyFour\CookieConnect\Core\Providers\Providers;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;
use NineteenNinetyFour\CookieConnect\Core\Settings\Validator;
use NineteenNinetyFour\CookieConnect\Core\Tests\Fixtures\Sites;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function providers(): array
    {
        return array_combine(array_keys(Sites::all()), array_map(fn ($h) => [$h], array_keys(Sites::all())));
    }

    #[DataProvider('providers')]
    public function test_the_fixture_values_are_valid(string $handle): void
    {
        $settings = new Settings(provider: $handle, values: Sites::all()[$handle]['values']);

        $this->assertSame([], (new Validator(Providers::default()))->problems($settings));
    }

    #[DataProvider('providers')]
    public function test_pasting_the_platforms_own_snippet_gives_the_same_values(string $handle): void
    {
        $site = Sites::all()[$handle];

        if ($site['pasted'] === []) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $normalised = (new Validator(Providers::default()))
            ->normalise(new Settings(provider: $handle, values: array_replace($site['values'], $site['pasted'])));

        foreach (array_keys($site['pasted']) as $field) {
            $this->assertSame($site['values'][$field], $normalised->values[$field], "{$handle}.{$field}");
        }
    }

    public function test_a_bare_id_is_kept_and_a_wrong_one_explained(): void
    {
        $cbid = Providers::default()->get('cookiebot')?->field('cbid');

        $this->assertInstanceOf(Field::class, $cbid);
        $this->assertSame('1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d', $cbid->normalise(' 1A2B3C4D-5E6F-7A8B-9C0D-1E2F3A4B5C6D '));
        $this->assertNull($cbid->problem('1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d'));
        $this->assertSame('Domain Group ID doesn’t look right. It should look like 1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d.', $cbid->problem('GTM-ABC123'));
        $this->assertSame('Domain Group ID is required.', $cbid->problem(''));
    }

    public function test_a_toggle_reads_what_forms_send(): void
    {
        $toggle = new Field('on', 'On', 'toggle');

        $this->assertTrue($toggle->normalise('1'));
        $this->assertTrue($toggle->normalise('true'));
        $this->assertFalse($toggle->normalise(''));
        $this->assertFalse($toggle->normalise(null));
    }

    public function test_onetrust_keeps_the_test_suffix(): void
    {
        $field = Providers::default()->get('onetrust')?->field('domainScript');

        $this->assertSame('fff8df06-1dd2-491b-88f6-01cae248cd17-test', $field?->normalise('data-domain-script="fff8df06-1dd2-491b-88f6-01cae248cd17-test"'));
        $this->assertNull($field?->problem('fff8df06-1dd2-491b-88f6-01cae248cd17-test'));
    }

    public function test_a_select_only_takes_its_options(): void
    {
        $cdn = Providers::default()->get('cookiebot')?->field('cdn');

        $this->assertNull($cdn?->problem('eu'));
        $this->assertNotNull($cdn?->problem('de'));
    }

    public function test_cookiefirst_domain_defaults_to_the_site(): void
    {
        $provider = Providers::default()->get('cookiefirst');

        $this->assertSame('example.com', $provider?->values(['apiKey' => 'x'], ['host' => 'example.com'])['domain']);
        $this->assertSame('other.org', $provider?->values(['apiKey' => 'x', 'domain' => 'other.org'], ['host' => 'example.com'])['domain']);
    }
}
