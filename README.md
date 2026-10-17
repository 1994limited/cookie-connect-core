# Cookie Connect Core

The framework-free core shared by [Cookie Connect for Craft CMS](https://github.com/1994limited/cookie-connect-craft) and [Cookie Connect for Statamic](https://github.com/1994limited/cookie-connect-statamic). You don't install it yourself; the plugin or addon requires it.

Cookie Connect connects a site to the cookie consent platform it already pays for (Cookiebot, Usercentrics, OneTrust, CookieYes, iubenda, Termly, CookieFirst, Cookie-Script, or any other), handles Google Consent Mode v2, and gives templates one way to mark scripts that need consent, whichever platform is behind it.

Each product stays thin: it stores the settings, draws the Control Panel, adds the template tags and puts the output into pages. Everything that's the same on both lives here:

- **Every platform, as data** (`resources/providers/*.yaml`): its fields and how to check them, its head tags, how it blocks scripts in each category, who sets Consent Mode defaults, its cookie declaration, the date it was last checked against the platform's docs. See [Providers](docs/providers.md).
- **The output** (`Output\Renderer`): the head block, consent-gated HTML (`gate()`), the declaration, "Cookie settings" links and placeholders, CSP hashes; and `Output\Injector`, which puts it all into a finished page.
- **The browser script** (`resources/runtime/cookie-connect.js`): reads consent from whichever platform the site uses and gives site code one API. See [The browser API](docs/javascript.md).
- **Settings and validation** (`Settings\*`): IDs taken out of pasted snippets, problems explained field by field.

Core depends on no framework or CMS. CI fails if `src/` names `Illuminate`, `Laravel`, `Craft`, `Yii` or `Statamic` (`bin/check-boundaries`).

## Using it

```php
use NineteenNinetyFour\CookieConnect\Core\Output\Context;
use NineteenNinetyFour\CookieConnect\Core\Output\Injector;
use NineteenNinetyFour\CookieConnect\Core\Output\Renderer;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;

$renderer = new Renderer(
    Settings::fromArray([
        'provider' => 'cookiebot',
        'values' => ['cbid' => '1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d'],
        'google' => ['gtmId' => 'GTM-ABC1234'],
    ]),
    new Context(host: 'example.com', lang: 'en', nonce: $nonce),
);

$html = Injector::inject($html, $renderer->head(), $renderer->bodyStart(), $renderer->bodyEnd());

echo $renderer->gate('<script src="https://plausible.io/js/script.js" defer></script>', 'analytics');
```

## Development

```bash
composer install
npm install
vendor/bin/phpunit      # PHP tests, with a snapshot of each provider's output (tests/Fixtures/snapshots)
npm test                # the browser script's tests (jsdom)
npm run build           # minifies the browser script; commit the result
vendor/bin/pint         # code style
vendor/bin/phpstan analyse
php bin/check-boundaries
```

After a deliberate change to the output, `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit` rewrites the snapshots; read the diff before committing.

## Licence

Commercial, as part of Cookie Connect. See [LICENSE.md](LICENSE.md).
