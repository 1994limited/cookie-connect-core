<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Output;

use NineteenNinetyFour\CookieConnect\Core\Category;
use NineteenNinetyFour\CookieConnect\Core\Output\Context;
use NineteenNinetyFour\CookieConnect\Core\Output\Renderer;
use NineteenNinetyFour\CookieConnect\Core\Settings\Script;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;
use NineteenNinetyFour\CookieConnect\Core\Tests\Fixtures\Sites;
use NineteenNinetyFour\CookieConnect\Core\Tests\Snapshots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GateTest extends TestCase
{
    use Snapshots;

    /** One of each kind of element the gate handles, and some it must leave alone. */
    public const SAMPLE = <<<'HTML'
        <script>console.log('inline');</script>
        <script src="https://cdn.example.com/tracker.js" async></script>
        <script type="module" src="https://cdn.example.com/app.mjs"></script>
        <script type="application/ld+json">{"@type":"Organization"}</script>
        <iframe src="https://www.youtube.com/embed/abc" width="560" height="315" class="video"></iframe>
        <img src="https://px.example.com/p.gif" alt="">
        <p>Text stays as it is.</p>
        HTML;

    /**
     * @return array<string, array{string}>
     */
    public static function providers(): array
    {
        return array_combine(array_keys(Sites::all()), array_map(fn ($h) => [$h], array_keys(Sites::all())));
    }

    private function renderer(string $provider, array $changes = []): Renderer
    {
        return new Renderer(
            new Settings(...array_replace(['provider' => $provider, 'values' => Sites::all()[$provider]['values']], $changes)),
            new Context('www.example.com', 'en', 'n0nce'),
        );
    }

    #[DataProvider('providers')]
    public function test_one_category(string $provider): void
    {
        $this->assertMatchesSnapshot("gate/{$provider}-analytics", $this->renderer($provider)->gate(self::SAMPLE, 'analytics'));
    }

    #[DataProvider('providers')]
    public function test_two_categories(string $provider): void
    {
        $this->assertMatchesSnapshot("gate/{$provider}-analytics-marketing", $this->renderer($provider)->gate(self::SAMPLE, ['analytics', 'marketing']));
    }

    public function test_necessary_content_is_left_alone_apart_from_the_nonce(): void
    {
        $out = $this->renderer('cookiebot')->gate('<script>a()</script><iframe src="x"></iframe>', 'necessary');

        $this->assertSame('<script nonce="n0nce">a()</script><iframe src="x"></iframe>', $out);
    }

    public function test_gating_twice_changes_nothing_the_second_time(): void
    {
        foreach (array_keys(Sites::all()) as $provider) {
            $once = $this->renderer($provider)->gate(self::SAMPLE, 'marketing');

            $this->assertSame($once, $this->renderer($provider)->gate($once, 'analytics'), $provider);
        }
    }

    public function test_a_mapping_override_changes_the_markup(): void
    {
        $out = $this->renderer('onetrust', ['categoryMap' => ['analytics' => '2']])->gate('<script>a()</script>', 'analytics');

        $this->assertStringContainsString('class="optanon-category-2"', $out);
    }

    public function test_when_off_content_is_kept_blocked_run_or_left_out(): void
    {
        $html = '<script>track()</script>';
        $off = fn (string $mode) => $this->renderer('cookiebot', ['active' => false, 'whenOff' => $mode])->gate($html, 'analytics');

        $this->assertSame('<script type="text/plain" data-cookie-connect="analytics" nonce="n0nce">track()</script>', $off('block'));
        $this->assertStringContainsString('hj()', $this->renderer('cookiebot', ['active' => false, 'scripts' => [new Script('Hotjar', [Category::Analytics], '<script>hj()</script>')]])->head(), 'Control Panel scripts stay on the page, inert, when blocked.');
        $this->assertSame($html, $off('run'));
        $this->assertSame('', $off('omit'));
        $this->assertSame($html, $this->renderer('cookiebot', ['active' => false, 'whenOff' => 'omit'])->gate($html, 'necessary'));
    }

    public function test_an_image_with_srcset_uses_cookie_connects_blocking(): void
    {
        $out = $this->renderer('cookiebot')->gate('<img src="a.jpg" srcset="a.jpg 1x, b.jpg 2x">', 'marketing');

        $this->assertSame('<img data-cookie-connect-src="a.jpg" data-cookie-connect-srcset="a.jpg 1x, b.jpg 2x" data-cookie-connect="marketing">', $out);
    }

    public function test_a_marker_alone_doesnt_stop_gating(): void
    {
        $out = $this->renderer('custom')->gate('<script data-cookie-connect="analytics">track()</script><img data-cookie-connect-native="x" src="p.gif">', 'marketing');

        $this->assertStringContainsString('<script data-cookie-connect="marketing" type="text/plain"', $out);
        $this->assertStringContainsString('data-cookie-connect-src="p.gif"', $out);
        $this->assertStringNotContainsString(' src="p.gif"', $out);
    }

    public function test_an_unknown_category_is_an_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown cookie category "statistics"');

        $this->renderer('cookiebot')->gate('<script></script>', 'statistics');
    }
}
