<?php

namespace NineteenNinetyFour\CookieConnect\Core\Html;

/**
 * An element found in a fragment of HTML: its name, its attributes in the
 * order they were written, and (for scripts) its contents. Attribute values
 * are kept as written (entities and all) so untouched ones come out as they
 * went in.
 */
final class Element
{
    /**
     * @param  array<string, string|true>  $attributes  name => value as written, or true for a bare attribute
     */
    public function __construct(
        public readonly string $name,
        private array $attributes = [],
        public ?string $content = null,
    ) {}

    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->attributes);
    }

    public function get(string $name): ?string
    {
        $value = $this->attributes[strtolower($name)] ?? null;

        return match (true) {
            $value === null => null,
            $value === true => '',
            default => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        };
    }

    public function set(string $name, string|bool $value): self
    {
        $name = strtolower($name);

        if ($value === false) {
            unset($this->attributes[$name]);
        } else {
            $this->attributes[$name] = $value === true ? true : htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->attributes[strtolower($name)]);

        return $this;
    }

    /**
     * Gives an attribute a new name, keeping its value and its place.
     */
    public function rename(string $from, string $to): self
    {
        $from = strtolower($from);
        $to = strtolower($to);

        if (! array_key_exists($from, $this->attributes)) {
            return $this;
        }

        $renamed = [];

        foreach ($this->attributes as $name => $value) {
            if ($name === $to) {
                continue;
            }

            $renamed[$name === $from ? $to : $name] = $value;
        }

        $this->attributes = $renamed;

        return $this;
    }

    public function addClass(string $classes): self
    {
        $current = preg_split('/\s+/', (string) $this->get('class'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $add = preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $this->set('class', implode(' ', array_values(array_unique([...$current, ...$add]))));
    }

    /**
     * @return array<string, string|true>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function openingTag(): string
    {
        $html = '<'.$this->name;

        foreach ($this->attributes as $name => $value) {
            $html .= $value === true ? " {$name}" : " {$name}=\"".str_replace('"', '&quot;', $value).'"';
        }

        return $html.'>';
    }
}
