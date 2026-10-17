<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Output;

use NineteenNinetyFour\CookieConnect\Core\Category;
use NineteenNinetyFour\CookieConnect\Core\Output\Context;
use NineteenNinetyFour\CookieConnect\Core\Output\Renderer;
use NineteenNinetyFour\CookieConnect\Core\Output\Runtime;
use NineteenNinetyFour\CookieConnect\Core\Settings\GoogleTags;
use NineteenNinetyFour\CookieConnect\Core\Settings\Script;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;
use NineteenNinetyFour\CookieConnect\Core\Tests\Fixtures\Sites;
use NineteenNinetyFour\CookieConnect\Core\Tests\Snapshots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase
{
    use Snapshots;

    /**
     * @return array<string, array{string}>
     */
    public static function providers(): array
    {
        return array_combine(array_keys(Sites::all()), array_map(fn ($h) => [$h], array_keys(Sites::all())));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $changes
     */
    private function renderer(string $provider, array $values = [], array $changes = [], ?string $nonce = null): Renderer
    {
        return new Renderer(
            new Settings(...array_replace([
                'provider' => $provider,
                'values' => array_replace(Sites::all()[$provider]['values'] ?? [], $values),
                'google' => new GoogleTags(gtmId: 'GTM-ABC1234', ga4Id: 'G-ABC123XYZ9'),
            ], $changes)),
            new Context('www.example.com', 'en-GB', $nonce),
        );
    }

    /** The head without the (long, separately tested) browser script. */
    private function head(Renderer $renderer): string
    {
        return str_replace(Runtime::source(), '/* runtime */', $renderer->head());
    }

    #[DataProvider('providers')]
    public function test_head(string $provider): void
    {
        $renderer = $this->renderer($provider);

        $this->assertSame('live', $renderer->state(), implode(' ', $renderer->problems()));
        $this->assertMatchesSnapshot("head/{$provider}", $this->head($renderer));
    }

    #[DataProvider('providers')]
    public function test_declaration(string $provider): void
    {
        $this->assertMatchesSnapshot("declaration/{$provider}", $this->renderer($provider)->declaration());
    }

    public function test_the_order_of_the_head(): void
    {
        $head = $this->head($this->renderer('cookiebot'));
        $at = fn (string $needle) => strpos($head, $needle);

        $this->assertLessThan($at('window.CookieConnectConfig'), $at("gtag('consent','default'"), 'Defaults come first.');
        $this->assertLessThan($at('consent.cookiebot.com/uc.js'), $at('window.CookieConnectConfig'), 'Then Cookie Connect, before the platform.');
        $this->assertLessThan($at('gtm.js'), $at('consent.cookiebot.com/uc.js'), 'Then the platform, then Google.');
    }

    public function test_onetrust_and_cookieyes_advanced_put_google_tags_before_the_platform(): void
    {
        $onetrust = $this->head($this->renderer('onetrust'));
        $this->assertLessThan(strpos($onetrust, 'otSDKStub.js'), strpos($onetrust, 'gtm.js'));

        $advanced = $this->head($this->renderer('cookieyes'));
        $this->assertLessThan(strpos($advanced, 'cdn-cookieyes.com'), strpos($advanced, 'gtm.js'));

        $basic = $this->head($this->renderer('cookieyes', [], ['google' => new GoogleTags(gtmId: 'GTM-ABC1234', mode: 'basic')]));
        $this->assertGreaterThan(strpos($basic, 'cdn-cookieyes.com'), strpos($basic, 'gtm.js'));
    }

    public function test_no_defaults_where_the_platform_sets_them(): void
    {
        $this->assertStringNotContainsString("'consent','default'", $this->head($this->renderer('iubenda')));
        $this->assertStringNotContainsString("'consent','default'", $this->head($this->renderer('termly')));
        $this->assertStringContainsString("'consent','default'", $this->head($this->renderer('termly', ['termlyConsentMode' => false])));
        $this->assertStringContainsString('TERMLY_FORCE_DISABLE_GCM', $this->head($this->renderer('termly', ['termlyConsentMode' => false])));
        $this->assertStringNotContainsString("'consent','default'", $this->head($this->renderer('cookiefirst', ['cookiefirstDefaults' => true])));
    }

    public function test_no_defaults_when_a_gtm_template_sets_them_or_consent_mode_is_off(): void
    {
        $gtm = $this->renderer('cookiebot', [], ['google' => new GoogleTags(gtmId: 'GTM-ABC1234', gtmSetsDefaults: true)]);
        $off = $this->renderer('cookiebot', [], ['google' => new GoogleTags(consentMode: false)]);

        $this->assertStringNotContainsString("'consent','default'", $this->head($gtm));
        $this->assertStringNotContainsString("'consent','default'", $this->head($off));
        $this->assertFalse($off->runtimeConfig()['consentMode']['update']);
    }

    public function test_the_defaults_deny_everything_but_security_and_wait_for_the_platform(): void
    {
        $head = $this->head($this->renderer('usercentrics'));

        $this->assertStringContainsString(
            "gtag('consent','default',{\"security_storage\":\"granted\",\"functionality_storage\":\"denied\",\"personalization_storage\":\"denied\",\"analytics_storage\":\"denied\",\"ad_storage\":\"denied\",\"ad_user_data\":\"denied\",\"ad_personalization\":\"denied\",\"wait_for_update\":2000});",
            $head,
        );
        $this->assertStringContainsString("gtag('set','ads_data_redaction',true);", $head);
        $this->assertStringNotContainsString('url_passthrough', $head);
    }

    public function test_regional_defaults(): void
    {
        $head = $this->head($this->renderer('cookiebot', [], ['google' => new GoogleTags(regions: 'eea', urlPassthrough: true, waitForUpdate: 750)]));

        $this->assertStringContainsString('"wait_for_update":750,"region":["AT","BE"', $head);
        $this->assertStringContainsString("gtag('consent','default',{\"security_storage\":\"granted\",\"functionality_storage\":\"granted\"", $head);
        $this->assertStringContainsString("gtag('set','url_passthrough',true);", $head);
    }

    public function test_we_send_updates_only_where_the_platform_does_not(): void
    {
        $this->assertFalse($this->renderer('cookiebot')->runtimeConfig()['consentMode']['update']);
        $this->assertTrue($this->renderer('custom')->runtimeConfig()['consentMode']['update']);
        $this->assertFalse($this->renderer('custom', ['cmpSendsUpdates' => true])->runtimeConfig()['consentMode']['update']);
        $this->assertTrue($this->renderer('termly', ['termlyConsentMode' => false])->runtimeConfig()['consentMode']['update']);
    }

    public function test_settings_change_the_platform_tags(): void
    {
        $auto = $this->head($this->renderer('cookiebot', ['autoBlocking' => true, 'tcf' => true, 'cdn' => 'eu']));
        $this->assertStringContainsString('src="https://consent.cookiebot.eu/uc.js" data-cbid="1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d" data-culture="en" data-blockingmode="auto" data-framework="TCF" type="text/javascript">', $auto);
        $this->assertStringContainsString('data-cookieconsent="ignore"', $auto, 'Our scripts are exempt from the auto-blocker.');

        $plain = $this->head($this->renderer('usercentrics'));
        $this->assertStringNotContainsString('data-draft', $plain, 'Presence-only attributes are left out when off.');
        $this->assertStringNotContainsString('data-tcf-enabled', $plain);
        $this->assertStringContainsString('data-tcf-enabled', $this->head($this->renderer('usercentrics', ['tcf' => true])));
        $this->assertStringContainsString('data-ruleset-id="y05NPaJsbGnT2v"', $this->head($this->renderer('usercentrics', ['idType' => 'ruleset'])));
        $this->assertStringContainsString('modules/autoblocker.js', $this->head($this->renderer('usercentrics', ['autoBlocking' => true])));

        $this->assertStringContainsString('cs.iubenda.com/autoblocking/1234567.js', $this->head($this->renderer('iubenda', ['autoBlocking' => true])));
        $this->assertStringContainsString('?autoBlock=off', $this->head($this->renderer('termly', ['autoBlocking' => false])));
        $this->assertStringContainsString('dev-cdn.cookie-script.com', $this->head($this->renderer('cookie-script', ['staging' => true])));
        $this->assertStringContainsString('cookie-cdn.cookiepro.com/scripttemplates/otSDKStub.js', $this->head($this->renderer('onetrust', ['cdn' => 'cookie-cdn.cookiepro.com'])));
        $this->assertStringContainsString('sites/example.com-5ba84e88', $this->head($this->renderer('cookiefirst', ['domain' => ''])), 'CookieFirst uses the site domain, without www.');
    }

    public function test_basic_mode_waits_for_analytics_before_loading_ga4(): void
    {
        $head = $this->head($this->renderer('cookiebot', [], ['google' => new GoogleTags(ga4Id: 'G-ABC123XYZ9', mode: 'basic')]));

        $this->assertStringContainsString('<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ9" type="text/plain" data-cookieconsent="statistics" data-cookie-connect-native="analytics"></script>', $head);
        $this->assertStringContainsString('<script type="text/plain" data-cookieconsent="statistics" data-cookie-connect-native="analytics">window.dataLayer', $head);
    }

    public function test_control_panel_scripts_go_where_they_are_placed_with_their_categories(): void
    {
        $renderer = $this->renderer('cookieyes', [], ['scripts' => [
            new Script('Hotjar', [Category::Analytics], '<script>hj()</script>', 'head'),
            new Script('Chat', [Category::Preferences], '<script src="https://chat.example.com/w.js"></script>', 'body_end'),
            new Script('Pixel', [Category::Marketing], '<img src="https://px.example.com/p.gif">', 'body_start'),
            new Script('Off', [Category::Marketing], '<script>off()</script>', 'head', false),
        ]]);

        $this->assertStringContainsString('<script type="text/plain" data-cookie-connect="analytics">hj()</script>', $renderer->head());
        $this->assertStringNotContainsString('off()', $renderer->head());
        $this->assertStringStartsWith('<!-- Cookie Connect: body start -->', $renderer->bodyStart());
        $this->assertStringContainsString('data-cookie-connect-src="https://px.example.com/p.gif" data-cookie-connect="marketing"', $renderer->bodyStart());
        $this->assertStringContainsString('<script src="https://chat.example.com/w.js" data-cookieyes="cookieyes-functional" data-cookie-connect-native="preferences"></script>', $renderer->bodyEnd());
        $this->assertSame('', $this->renderer('cookieyes')->bodyEnd());
    }

    public function test_off_and_unset_sites_print_only_the_browser_script(): void
    {
        foreach ([
            'off' => $this->renderer('cookiebot', [], ['active' => false]),
            'none' => new Renderer(new Settings, new Context('example.com')),
            'unknown' => new Renderer(new Settings(provider: 'nope'), new Context('example.com')),
            'incomplete' => $this->renderer('cookiebot', ['cbid' => 'wrong']),
        ] as $state => $renderer) {
            $head = $this->head($renderer);

            $this->assertSame($state, $renderer->state());
            $this->assertStringContainsString('window.CookieConnectConfig={"version"', $head, $state);
            $this->assertStringContainsString('"provider":null,"state":"block"', $head, $state);
            $this->assertStringNotContainsString('gtm.js', $head, $state);
            $this->assertStringNotContainsString('cookiebot.com', $head, $state);
            $this->assertSame('', $renderer->declaration(), $state);
        }
    }

    public function test_off_and_set_to_run_prints_google_tags_without_consent(): void
    {
        $head = $this->head($this->renderer('cookiebot', [], ['active' => false, 'whenOff' => 'run']));

        $this->assertStringContainsString('gtm.js', $head);
        $this->assertStringNotContainsString("'consent','default'", $head);
        $this->assertStringContainsString('"state":"run"', $head);
    }

    public function test_the_nonce_goes_on_every_script_and_hashes_match_the_inline_ones(): void
    {
        $renderer = $this->renderer('onetrust', [], ['scripts' => [new Script('Hotjar', [Category::Analytics], '<script>hj()</script>')]], 'n0nce');
        $head = $renderer->head();

        preg_match_all('/<script\b[^>]*>/', $head, $tags);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="n0nce"', $tag);
        }

        preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/s', $head, $inline);
        $expected = array_map(fn ($code) => "'sha256-".base64_encode(hash('sha256', $code, true))."'", array_filter($inline[1], fn ($c) => trim($c) !== ''));

        $this->assertEqualsCanonicalizing(array_values(array_unique($expected)), $renderer->cspHashes());
    }

    public function test_custom_prints_its_code_with_its_hooks(): void
    {
        $head = $this->head($this->renderer('custom', [], [], 'n0nce'));

        $this->assertStringContainsString('<script src="https://cmp.example.com/loader.js" nonce="n0nce"></script>', $head);
        $this->assertStringContainsString('CookieConnect.onOpen(function () { ExampleCmp.open(); });', $head);
        $this->assertStringContainsString("window.addEventListener('example-consent'", $head);
    }

    public function test_a_script_ending_tag_in_a_value_cannot_break_out(): void
    {
        $head = $this->head($this->renderer('custom', ['openJs' => 'x("</script><script>alert(1)</script>")']));

        $this->assertStringNotContainsString('</script><script>alert(1)', $head);
    }

    public function test_settings_link_and_placeholder(): void
    {
        $this->assertSame(
            '<button type="button" class="cookie-connect-settings cky-banner-element footer-link" data-cookie-connect-open>Cookie settings</button>',
            $this->renderer('cookieyes')->settingsLink('Cookie settings', ['class' => 'footer-link']),
        );
        $this->assertSame(
            '<a href="#cookie-settings" class="cookie-connect-settings" data-cookie-connect-open>Cookie &amp; privacy</a>',
            $this->renderer('cookiebot')->settingsLink('Cookie & privacy', [], 'a'),
        );
        $this->assertSame(
            '<div class="cookie-connect-placeholder" data-cookie-connect-placeholder="marketing"><p>This video needs marketing cookies.</p><button type="button" class="cookie-connect-settings" data-cookie-connect-open>Cookie settings</button></div>',
            $this->renderer('onetrust')->placeholder('marketing', 'This video needs marketing cookies.', 'Cookie settings'),
        );
    }

    public function test_tokens_in_content_become_the_declaration_and_a_settings_link(): void
    {
        $renderer = $this->renderer('onetrust');
        $html = '<p>{cookie_declaration}</p><p>Change your mind: {cookie_settings}.</p><p>{cookie_other}</p>';

        $this->assertSame(
            '<div class="cookie-connect-declaration">'."\n".'<div id="ot-sdk-cookie-policy"></div>'."\n".'</div>'
            .'<p>Change your mind: <a href="#cookie-settings" class="cookie-connect-settings" data-cookie-connect-open>Cookie settings</a>.</p><p>{cookie_other}</p>',
            $renderer->replaceTokens($html, 'Cookie settings'),
        );
    }

    public function test_the_context_tidies_host_and_language(): void
    {
        $context = new Context('https://WWW.Example.com:8080/path', 'en-GB');

        $this->assertSame('example.com', $context->host);
        $this->assertSame('en', $context->lang);
        $this->assertSame('de', (new Context('', 'de_DE'))->lang);
    }
}
