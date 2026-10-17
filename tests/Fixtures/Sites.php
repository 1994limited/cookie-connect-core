<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Fixtures;

/**
 * A working set of values for every provider, and the snippet each
 * platform's dashboard gives you to paste (from its docs), so tests can
 * check that pasting the snippet gives the same values.
 */
final class Sites
{
    /**
     * @return array<string, array{values: array<string, mixed>, pasted: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'cookiebot' => [
                'values' => ['cbid' => '1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d', 'cdn' => 'com', 'autoBlocking' => false, 'tcf' => false],
                'pasted' => ['cbid' => '<script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="1A2B3C4D-5E6F-7A8B-9C0D-1E2F3A4B5C6D" type="text/javascript" async></script>'],
            ],
            'usercentrics' => [
                'values' => ['settingsId' => 'y05NPaJsbGnT2v', 'idType' => 'settings', 'autoBlocking' => false, 'tcf' => false, 'draft' => false],
                'pasted' => ['settingsId' => '<script id="usercentrics-cmp" data-settings-id="y05NPaJsbGnT2v" src="https://web.cmp.usercentrics.eu/ui/loader.js" async></script>'],
            ],
            'onetrust' => [
                'values' => ['domainScript' => 'fff8df06-1dd2-491b-88f6-01cae248cd17', 'cdn' => 'cdn.cookielaw.org', 'autoBlocking' => false, 'documentLanguage' => true],
                'pasted' => ['domainScript' => "<!-- OneTrust Cookies Consent Notice start -->\n<script src=\"https://cdn.cookielaw.org/scripttemplates/otSDKStub.js\" type=\"text/javascript\" charset=\"UTF-8\" data-domain-script=\"fff8df06-1dd2-491b-88f6-01cae248cd17\"></script>\n<script type=\"text/javascript\">function OptanonWrapper() { }</script>"],
            ],
            'cookieyes' => [
                'values' => ['websiteKey' => 'e5ee5d26e0341217ffb7eccd'],
                'pasted' => ['websiteKey' => '<!-- Start cookieyes banner --><script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/e5ee5d26e0341217ffb7eccd/script.js"></script><!-- End cookieyes banner -->'],
            ],
            'iubenda' => [
                'values' => ['widgetCode' => '8a1b2c3d-4e5f-6789-abcd-ef0123456789', 'siteId' => '1234567', 'cookiePolicyId' => '12345678', 'autoBlocking' => false],
                'pasted' => [
                    'widgetCode' => '<script src="https://embeds.iubenda.com/widgets/8a1b2c3d-4e5f-6789-abcd-ef0123456789.js"></script>',
                    'siteId' => '<script type="text/javascript" src="https://cs.iubenda.com/autoblocking/1234567.js"></script>',
                    'cookiePolicyId' => '<a href="https://www.iubenda.com/privacy-policy/12345678/cookie-policy" class="iubenda-white iubenda-noiframe iubenda-embed" title="Cookie Policy">Cookie Policy</a>',
                ],
            ],
            'termly' => [
                'values' => ['websiteUuid' => '1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d', 'autoBlocking' => true, 'termlyConsentMode' => true, 'policyUuid' => '9f8e7d6c-5b4a-3928-1706-f5e4d3c2b1a0'],
                'pasted' => [
                    'websiteUuid' => '<script type="text/javascript" src="https://app.termly.io/resource-blocker/1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d?autoBlock=on"></script>',
                    'policyUuid' => '<div name="termly-embed" data-id="9f8e7d6c-5b4a-3928-1706-f5e4d3c2b1a0" data-type="iframe"></div>',
                ],
            ],
            'cookiefirst' => [
                'values' => ['apiKey' => '5ba84e88-816a-41b8-88b3-e8ec9218d098', 'domain' => 'suerc.ac.uk', 'cookiefirstDefaults' => false],
                'pasted' => ['apiKey' => '<script src="https://consent.cookiefirst.com/sites/suerc.ac.uk-5ba84e88-816a-41b8-88b3-e8ec9218d098/consent.js"></script>', 'domain' => ''],
            ],
            'cookie-script' => [
                'values' => ['scriptId' => 'b62c08ab61b84e127d792f54b324dd70', 'staging' => false, 'reportId' => ''],
                'pasted' => ['scriptId' => '<script type="text/javascript" charset="UTF-8" src="//cdn.cookie-script.com/s/b62c08ab61b84e127d792f54b324dd70.js"></script>'],
            ],
            'custom' => [
                'values' => [
                    'headHtml' => '<script src="https://cmp.example.com/loader.js"></script>',
                    'connectJs' => "window.addEventListener('example-consent', function (e) { CookieConnect.set(e.detail); });",
                    'openJs' => 'ExampleCmp.open();',
                    'cmpSetsDefaults' => false,
                    'cmpSendsUpdates' => false,
                ],
                'pasted' => [],
            ],
        ];
    }
}
