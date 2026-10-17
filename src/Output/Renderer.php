<?php

namespace NineteenNinetyFour\CookieConnect\Core\Output;

use NineteenNinetyFour\CookieConnect\Core\Category;
use NineteenNinetyFour\CookieConnect\Core\Html\Element;
use NineteenNinetyFour\CookieConnect\Core\Html\Fragment;
use NineteenNinetyFour\CookieConnect\Core\Html\Tag;
use NineteenNinetyFour\CookieConnect\Core\Providers\Conditions;
use NineteenNinetyFour\CookieConnect\Core\Providers\Provider;
use NineteenNinetyFour\CookieConnect\Core\Providers\Providers;
use NineteenNinetyFour\CookieConnect\Core\Providers\Template;
use NineteenNinetyFour\CookieConnect\Core\Settings\GoogleTags;
use NineteenNinetyFour\CookieConnect\Core\Settings\Script;
use NineteenNinetyFour\CookieConnect\Core\Settings\Settings;
use NineteenNinetyFour\CookieConnect\Core\Settings\Validator;

/**
 * Everything Cookie Connect prints, for one site and one request.
 *
 * The head block, in this order: Consent Mode defaults (unless the platform
 * sets them), Cookie Connect's script, the platform's tags, Google Tag
 * Manager and Analytics (before the platform where its docs ask for that),
 * then the Control Panel's head scripts. The body blocks carry the other
 * Control Panel scripts.
 *
 * When the site is off (no platform chosen, a required setting missing, or
 * an environment it doesn't run on), the head carries only Cookie Connect's
 * script, so site code that uses window.CookieConnect still works, and
 * consent-gated content is kept blocked, run or left out (Settings::$whenOff).
 */
final class Renderer
{
    private readonly Providers $providers;

    private readonly ?Provider $provider;

    /** @var array<string, mixed> */
    private readonly array $values;

    /** @var list<string> */
    private array $inline = [];

    private ?string $page = null;

    private bool $printed = false;

    public function __construct(
        private readonly Settings $settings,
        private readonly Context $context = new Context,
        ?Providers $providers = null,
    ) {
        $this->providers = $providers ?? Providers::default();
        $this->provider = $this->providers->get($settings->provider);
        $this->values = $this->provider?->values($settings->values, [
            'host' => $context->host,
            'lang' => $context->lang,
            'mode' => $settings->google->mode,
        ]) ?? [];
    }

    public function provider(): ?Provider
    {
        return $this->provider;
    }

    /**
     * "live", or why it isn't: "off" (this environment), "none" (no platform
     * chosen), "unknown" (a platform this version doesn't know) or
     * "incomplete" (a setting is missing or wrong).
     */
    public function state(): string
    {
        return match (true) {
            ! $this->settings->active => 'off',
            $this->settings->provider === null => 'none',
            $this->provider === null => 'unknown',
            $this->blockingProblems() !== [] => 'incomplete',
            default => 'live',
        };
    }

    public function isLive(): bool
    {
        return $this->state() === 'live';
    }

    /**
     * @return array<string, string>
     */
    public function problems(): array
    {
        return (new Validator($this->providers))->problems($this->settings);
    }

    /**
     * The problems that stop the platform loading: its own settings. A bad
     * Google ID or script is left out on its own instead, so one mistake
     * there doesn't take the consent platform off the site.
     *
     * @return array<string, string>
     */
    public function blockingProblems(): array
    {
        return array_filter($this->problems(), fn (string $path) => $path === 'provider' || str_starts_with($path, 'values.'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  string|null  $page  the rest of the page, when known: Google tags already on it (from SEOmatic, another
     *                             plugin or a template) aren't added again
     */
    public function head(?string $page = null): string
    {
        $this->page = $page;
        $this->printed = true;
        $live = $this->isLive();
        $parts = [];

        if ($live && $this->sendsDefaults()) {
            $parts[] = $this->script($this->defaultsCode(), $this->exempt());
        }

        $parts[] = $this->script(
            'window.CookieConnectConfig='.$this->json($this->runtimeConfig()).";\n".Runtime::source(),
            $this->exempt() + ['data-cookie-connect-runtime' => true],
        );

        if ($live) {
            $first = $this->provider?->googleTagsFirst($this->values) ?? false;

            if ($first) {
                $parts[] = $this->googleTags();
            }

            foreach ($this->provider->head ?? [] as $spec) {
                $parts[] = $this->spec($spec);
            }

            if (! $first) {
                $parts[] = $this->googleTags();
            }
        } elseif ($this->settings->whenOff === 'run') {
            $parts[] = $this->googleTags();
        }

        $parts[] = $this->scripts('head');

        return Injector::HEAD."\n".implode("\n", array_filter($parts))."\n<!-- /Cookie Connect -->";
    }

    public function bodyStart(): string
    {
        $scripts = $this->scripts('body_start');

        return $scripts === '' ? '' : Injector::BODY_START."\n".$scripts;
    }

    public function bodyEnd(): string
    {
        $scripts = $this->scripts('body_end');

        return $scripts === '' ? '' : Injector::BODY_END."\n".$scripts;
    }

    /**
     * Marks up the scripts, iframes and images in $html so they wait for the
     * visitor's consent to every category given.
     *
     * @param  string|list<string|Category>  $categories
     */
    public function gate(string $html, string|array $categories): string
    {
        $categories = Category::list($categories);

        if (! $this->isLive()) {
            return match ($this->settings->whenOff) {
                'run' => $html,
                'omit' => array_filter($categories, fn (Category $c) => $c !== Category::Necessary) === [] ? $html : '',
                default => (new Gate(null, [], $this->context->nonce))->apply($html, $categories),
            };
        }

        return $this->track((new Gate(
            $this->provider,
            $this->provider?->categoryMap($this->settings->categoryMap) ?? [],
            $this->context->nonce,
        ))->apply($html, $categories));
    }

    /**
     * The platform's cookie declaration (the list of cookies the site uses),
     * or an empty string when it has none or the site isn't live.
     */
    public function declaration(): string
    {
        if (! $this->isLive() || $this->provider === null || ! $this->provider->hasDeclaration()) {
            return '';
        }

        $html = implode("\n", array_filter(array_map(fn (array $spec) => $this->spec($spec), $this->provider->declaration)));

        return $html === '' ? '' : Tag::make('div', ['class' => 'cookie-connect-declaration'], "\n".$html."\n");
    }

    /**
     * A control that opens the platform's cookie preferences.
     *
     * @param  array<string, string|bool|null>  $attributes
     */
    public function settingsLink(string $text, array $attributes = [], string $tag = 'button'): string
    {
        $class = implode(' ', array_filter(['cookie-connect-settings', $this->isLive() ? $this->provider?->preferencesClass : null, is_string($attributes['class'] ?? null) ? trim($attributes['class']) : null]));
        unset($attributes['class']);

        $base = $tag === 'a'
            ? ['href' => '#cookie-settings', 'class' => $class, 'data-cookie-connect-open' => true]
            : ['type' => 'button', 'class' => $class, 'data-cookie-connect-open' => true];

        return Tag::make($tag === 'a' ? 'a' : 'button', $base + $attributes, Tag::text($text));
    }

    /**
     * A notice shown in place of blocked content until the visitor allows
     * it. Cookie Connect's script hides it once they do.
     *
     * @param  string|list<string|Category>  $categories
     */
    public function placeholder(string|array $categories, string $text, string $button): string
    {
        $categories = implode(' ', array_map(fn (Category $c) => $c->value, Category::list($categories)));

        return Tag::make('div', ['class' => 'cookie-connect-placeholder', 'data-cookie-connect-placeholder' => $categories],
            Tag::make('p', [], Tag::text($text)).$this->settingsLink($button));
    }

    /**
     * Swaps the tokens editors can type into content for the real thing:
     * {cookie_declaration} for the declaration and {cookie_settings} for a
     * Cookie settings link. A token wrapped in its own paragraph loses the
     * paragraph, so the declaration isn't nested inside one.
     */
    public function replaceTokens(string $html, string $settingsText): string
    {
        if (! str_contains($html, '{cookie_')) {
            return $html;
        }

        // Text between tags only: never inside a tag, a script, a style or a textarea.
        $parts = preg_split('~(<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<!--.*?-->|<[^>]*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1 || ! str_contains($part, '{cookie_')) {
                continue;
            }

            // A declaration alone in a paragraph takes the paragraph's place.
            if (trim($part) === '{cookie_declaration}' && preg_match('~^<p(\s[^>]*)?>$~i', $parts[$i - 1] ?? '') && preg_match('~^</p\s*>$~i', $parts[$i + 1] ?? '')) {
                $parts[$i - 1] = '';
                $parts[$i + 1] = '';
                $parts[$i] = $this->declaration();

                continue;
            }

            $parts[$i] = strtr($part, [
                '{cookie_declaration}' => $this->declaration(),
                '{cookie_settings}' => $this->settingsLink($settingsText, [], 'a'),
            ]);
        }

        return implode('', $parts);
    }

    /**
     * What the browser script is told.
     *
     * @return array<string, mixed>
     */
    public function runtimeConfig(): array
    {
        $live = $this->isLive();
        $types = [];

        foreach (Category::cases() as $category) {
            $types[$category->value] = $category->consentTypes();
        }

        return [
            'version' => Runtime::VERSION,
            'provider' => $live ? $this->provider?->handle : null,
            'state' => $live ? 'live' : $this->settings->whenOff,
            'map' => $live ? ($this->provider?->categoryMap($this->settings->categoryMap) ?? []) : (object) [],
            'nativeClass' => $live ? $this->provider?->preferencesClass : null,
            'consentMode' => [
                'update' => $live && $this->settings->google->consentMode && ! ($this->provider?->sendsUpdates($this->values) ?? true),
                'types' => $types,
            ],
            'debug' => $this->settings->debug,
        ];
    }

    /**
     * The contents of every inline script printed so far (call after the
     * blocks you print), for a Content Security Policy that uses hashes.
     *
     * @return list<string>
     */
    public function inlineScripts(): array
    {
        return array_values(array_unique($this->inline));
    }

    /**
     * 'sha256-…' sources for the inline scripts Cookie Connect prints (its
     * own, the platform's, Google's and the Control Panel's scripts), and
     * those held back with gate() so far.
     *
     * @return list<string>
     */
    public function cspHashes(): array
    {
        if (! $this->printed) {
            // Not printed yet (it's added to the page after templates render): work them out.
            $this->head($this->page);
            $this->bodyStart();
            $this->bodyEnd();
        }

        return array_map(fn (string $code) => "'sha256-".base64_encode(hash('sha256', $code, true))."'", $this->inlineScripts());
    }

    private function sendsDefaults(): bool
    {
        $google = $this->settings->google;

        return $google->consentMode && ! $google->gtmSetsDefaults && ! ($this->provider?->setsDefaults($this->values) ?? false);
    }

    private function defaultsCode(): string
    {
        $google = $this->settings->google;
        $denied = [];

        foreach (Category::cases() as $category) {
            foreach ($category->consentTypes() as $type) {
                $denied[$type] = $category === Category::Necessary ? 'granted' : 'denied';
            }
        }

        $wait = $google->waitForUpdate ?? $this->provider?->waitForUpdate() ?? 500;
        $code = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n";

        if ($google->regions === 'eea') {
            $code .= "gtag('consent','default',".$this->json($denied + ['wait_for_update' => $wait, 'region' => GoogleTags::EEA]).");\n";
            $code .= "gtag('consent','default',".$this->json(array_map(fn () => 'granted', $denied)).");\n";
        } else {
            $code .= "gtag('consent','default',".$this->json($denied + ['wait_for_update' => $wait]).");\n";
        }

        if ($google->adsDataRedaction) {
            $code .= "gtag('set','ads_data_redaction',true);\n";
        }

        if ($google->urlPassthrough) {
            $code .= "gtag('set','url_passthrough',true);\n";
        }

        return rtrim($code);
    }

    private function googleTags(): string
    {
        $google = $this->settings->google;
        $parts = [];

        $gtmId = $google->gtmId !== null && preg_match(GoogleTags::GTM_PATTERN, $google->gtmId) ? $google->gtmId : null;
        $ga4Id = $google->ga4Id !== null && preg_match(GoogleTags::GA4_PATTERN, $google->ga4Id) ? $google->ga4Id : null;

        foreach (array_filter([$gtmId, $ga4Id]) as $id) {
            if ($this->onPage($id)) {
                $parts[] = "<!-- Cookie Connect: {$id} is already on this page, so it isn't added again. -->";
            }
        }

        if ($gtmId !== null && ! $this->onPage($gtmId)) {
            $id = $this->json($gtmId);
            $parts[] = $this->script(
                "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});"
                ."var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'',n=d.currentScript&&d.currentScript.nonce;"
                ."j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;if(n)j.setAttribute('nonce',n);f.parentNode.insertBefore(j,f);"
                ."})(window,document,'script','dataLayer',{$id});",
                $this->exempt(),
            );
        }

        if ($ga4Id !== null && ! $this->onPage($ga4Id)) {
            $id = $this->json($ga4Id);
            $ga4 = Tag::make('script', ['async' => true, 'src' => 'https://www.googletagmanager.com/gtag/js?id='.rawurlencode($ga4Id)])
                ."\n".Tag::make('script', [], "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',{$id});");

            // Without Consent Mode, or in basic mode, Analytics waits for consent to analytics.
            if ((! $google->consentMode || $google->mode === 'basic') && $this->isLive()) {
                $parts[] = $this->track($this->gate($ga4, [Category::Analytics]));
            } else {
                $parts[] = $this->track($this->withAttributes($ga4, $this->exempt()));
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Whether a Google tag ID is loaded by something else on the page.
     */
    private function onPage(string $id): bool
    {
        if ($this->page === null) {
            return false;
        }

        // Its ID next to a Google loader (gtm.js, gtag.js), or quoted in a script: not a mention in text.
        $id = preg_quote($id, '~');

        return (bool) preg_match('~googletagmanager\.com/(?:gtm\.js|gtag/js)\?id='.$id.'\b|[\'"]'.$id.'[\'"]~', $this->page);
    }

    private function scripts(string $placement): string
    {
        if (! $this->isLive() && $this->settings->whenOff !== 'run') {
            return '';
        }

        $html = array_map(
            fn (Script $script) => $this->track($this->gate(trim($script->code), $script->categories)),
            array_values(array_filter($this->settings->scripts, fn (Script $s) => $s->enabled && $s->placement === $placement && $s->isUsable())),
        );

        return implode("\n", $html);
    }

    /**
     * One tag from a provider file.
     *
     * @param  array<string, mixed>  $spec
     */
    private function spec(array $spec): string
    {
        if (! Conditions::matches($spec['when'] ?? null, $this->values)) {
            return '';
        }

        if (isset($spec['raw'])) {
            return $this->track($this->withAttributes(Template::fill((string) $spec['raw'], $this->values), []));
        }

        $name = (string) $spec['tag'];
        $attributes = [];

        foreach ((array) ($spec['attrs'] ?? []) as $attribute => $value) {
            $value = Conditions::resolve($value, $this->values);

            if ($value === null || $value === false) {
                continue;
            }

            $value = $value === true ? true : Template::fill((string) $value, $this->values);

            if ($value !== '') {
                $attributes[(string) $attribute] = $value;
            }
        }

        $content = null;

        if (isset($spec['content'])) {
            $content = $name === 'script'
                ? Template::fill((string) $spec['content'], $this->values, fn (string $v, string $mod) => $mod === 'raw' ? $v : $this->json($v))
                : Template::fill((string) $spec['content'], $this->values, fn (string $v) => Tag::text($v));
        }

        if ($name === 'script') {
            return $content === null
                ? Tag::make('script', $attributes + $this->nonce())
                : $this->script($content, $attributes);
        }

        return Tag::make($name, $attributes, $content);
    }

    /**
     * @param  array<string, string|bool|null>  $attributes
     */
    private function script(string $code, array $attributes = []): string
    {
        $code = Tag::script($code);
        $this->inline[] = $code;

        return Tag::make('script', $attributes + $this->nonce(), $code);
    }

    /**
     * Notes the inline scripts in some HTML (for CSP hashes) and returns it.
     */
    private function track(string $html): string
    {
        foreach (Fragment::find($html) as $element) {
            if ($element->name === 'script' && ! $element->has('src') && trim((string) $element->content) !== '') {
                $this->inline[] = (string) $element->content;
            }
        }

        return $html;
    }

    /**
     * Adds attributes (and the nonce) to the scripts in some HTML.
     *
     * @param  array<string, string>  $attributes
     */
    private function withAttributes(string $html, array $attributes): string
    {
        $attributes += array_filter($this->nonce(), 'is_string');

        if ($attributes === []) {
            return $html;
        }

        return Fragment::rewrite($html, function (Element $element) use ($attributes) {
            if ($element->name === 'script') {
                foreach ($attributes as $name => $value) {
                    if (! $element->has($name)) {
                        $element->set($name, $value);
                    }
                }
            }

            return $element;
        });
    }

    /**
     * @return array<string, string>
     */
    private function exempt(): array
    {
        return $this->isLive() ? ($this->provider?->exemptAttributes($this->values) ?? []) : [];
    }

    /**
     * @return array<string, string>
     */
    private function nonce(): array
    {
        return $this->context->nonce === null ? [] : ['nonce' => $this->context->nonce];
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
