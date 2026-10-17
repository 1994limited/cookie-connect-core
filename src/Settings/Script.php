<?php

namespace NineteenNinetyFour\CookieConnect\Core\Settings;

use NineteenNinetyFour\CookieConnect\Core\Category;

/**
 * A script an editor added in the Control Panel (Hotjar, a Meta pixel, a
 * chat widget…), printed with the right blocking markup for its category,
 * so no template has to change.
 */
final class Script
{
    public const PLACEMENTS = ['head', 'body_start', 'body_end'];

    /**
     * @param  list<Category>  $categories
     */
    public function __construct(
        public readonly string $name,
        public readonly array $categories,
        public readonly string $code,
        public readonly string $placement = 'head',
        public readonly bool $enabled = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $categories = $data['categories'] ?? $data['category'] ?? 'necessary';

        try {
            $categories = Category::list(is_array($categories) ? array_values($categories) : (string) $categories);
        } catch (\InvalidArgumentException) {
            $categories = [Category::Marketing];
        }

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            categories: $categories ?: [Category::Necessary],
            code: (string) ($data['code'] ?? ''),
            placement: in_array($data['placement'] ?? null, self::PLACEMENTS, true) ? $data['placement'] : 'head',
            enabled: filter_var($data['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'categories' => array_map(fn (Category $c) => $c->value, $this->categories),
            'code' => $this->code,
            'placement' => $this->placement,
            'enabled' => $this->enabled,
        ];
    }
}
