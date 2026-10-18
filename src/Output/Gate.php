<?php

namespace NineteenNinetyFour\CookieConnect\Core\Output;

use NineteenNinetyFour\CookieConnect\Core\Category;
use NineteenNinetyFour\CookieConnect\Core\Html\Element;
use NineteenNinetyFour\CookieConnect\Core\Html\Fragment;
use NineteenNinetyFour\CookieConnect\Core\Providers\Provider;
use NineteenNinetyFour\CookieConnect\Core\Providers\Template;

/**
 * Marks up the scripts, iframes and images in some HTML so they only run
 * once the visitor allows their categories.
 *
 * Where the consent platform can do it ("native"), the markup is the
 * platform's own and the platform runs them. Where it can't (CookieYes's
 * inline scripts, Usercentrics' service-based blocking, the Custom
 * provider, several categories on platforms that take one), the markup is
 * Cookie Connect's and its script runs them. Each element has one owner, so
 * nothing runs twice.
 */
final class Gate
{
    public const EXECUTABLE = ['', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript'];

    /**
     * @param  Provider|null  $provider  null: Cookie Connect's own markup throughout
     * @param  array<string, string|null>  $map  our category => the provider's id
     */
    public function __construct(
        private readonly ?Provider $provider,
        private readonly array $map = [],
        private readonly ?string $nonce = null,
    ) {}

    /**
     * @param  list<Category>  $categories  all of them are needed
     */
    public function apply(string $html, array $categories): string
    {
        $optional = array_values(array_filter($categories, fn (Category $c) => $c !== Category::Necessary));

        return Fragment::rewrite($html, function (Element $element) use ($optional) {
            $kind = $this->kind($element);

            if ($kind === null) {
                return $element;
            }

            if ($optional === []) {
                return $this->withNonce($element);
            }

            $rule = $this->rule($kind, $element, $optional);

            return $rule === null
                ? $this->managed($element, $optional)
                : $this->native($element, $optional, $rule);
        });
    }

    /**
     * Which kind of element this is for blocking, or null to leave it alone
     * (JSON-LD, templates, already marked up, nothing to load).
     */
    private function kind(Element $element): ?string
    {
        // Already held back by an earlier gate. A marker alone isn't enough (content could carry one):
        // scripts must also be inert (below), iframes and images must have nothing left to load.
        if ($element->name !== 'script' && ($element->has('data-cookie-connect') || $element->has('data-cookie-connect-native'))
            && (! $element->has('src') || $element->get('src') === 'about:blank') && ! $element->has('srcset')) {
            return null;
        }

        if ($element->name === 'script') {
            $type = strtolower(trim((string) $element->get('type')));

            if (! in_array($type, self::EXECUTABLE, true)) {
                return null;
            }

            return $element->has('src') ? 'script_src' : 'script_inline';
        }

        if (! $element->has('src') && ! $element->has('srcset')) {
            return null;
        }

        return $element->name;
    }

    /**
     * The provider's rule for this element, or null when Cookie Connect has
     * to do the blocking itself.
     *
     * @param  list<Category>  $categories
     * @return array<string, mixed>|null
     */
    private function rule(string $kind, Element $element, array $categories): ?array
    {
        if ($this->provider === null) {
            return null;
        }

        $rule = $this->provider->blocking[$kind] ?? null;

        if (! is_array($rule) || ($rule['strategy'] ?? 'managed') !== 'native') {
            return null;
        }

        if (strtolower((string) $element->get('type')) === 'module') {
            return null;
        }

        // Platforms swap src, not srcset: an image with one would load anyway.
        if ($element->has('srcset')) {
            return null;
        }

        if (($this->provider->blocking['multiple'] ?? true) === false && count($categories) > 1) {
            return null;
        }

        foreach ($categories as $category) {
            if (($this->map[$category->value] ?? null) === null) {
                return null;
            }
        }

        return $rule;
    }

    /**
     * @param  list<Category>  $categories
     * @param  array<string, mixed>  $rule
     */
    private function native(Element $element, array $categories, array $rule): Element
    {
        $theirs = implode(
            (string) ($this->provider?->blocking['join'] ?? ','),
            array_map(fn (Category $c) => (string) $this->map[$c->value], $categories),
        );

        foreach ((array) ($rule['rename'] ?? []) as $from => $to) {
            $element->rename((string) $from, (string) $to);
        }

        foreach ((array) ($rule['attrs'] ?? []) as $name => $template) {
            $value = Template::fill((string) $template, ['categories' => $theirs]);
            $name === 'class' ? $element->addClass($value) : $element->set((string) $name, $value);
        }

        $element->set('data-cookie-connect-native', $this->ours($categories));

        return $this->withNonce($element);
    }

    /**
     * @param  list<Category>  $categories
     */
    private function managed(Element $element, array $categories): Element
    {
        if ($element->name === 'script') {
            $type = strtolower(trim((string) $element->get('type')));

            if ($type !== '' && $type !== 'text/javascript') {
                $element->set('data-cookie-connect-type', $type);
            }

            $element->set('type', 'text/plain');
        }

        if ($element->name !== 'script' && $element->get('src') === 'about:blank') {
            $element->remove('src');
        }

        $element->rename('src', 'data-cookie-connect-src');
        $element->rename('srcset', 'data-cookie-connect-srcset');
        $element->set('data-cookie-connect', $this->ours($categories));

        return $this->withNonce($element);
    }

    /**
     * @param  list<Category>  $categories
     */
    private function ours(array $categories): string
    {
        return implode(' ', array_map(fn (Category $c) => $c->value, $categories));
    }

    private function withNonce(Element $element): Element
    {
        if ($this->nonce !== null && $element->name === 'script' && ! $element->has('nonce')) {
            $element->set('nonce', $this->nonce);
        }

        return $element;
    }
}
