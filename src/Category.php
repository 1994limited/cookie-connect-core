<?php

namespace NineteenNinetyFour\CookieConnect\Core;

/**
 * The four categories every template and setting uses, whichever consent
 * platform the site has. Each provider maps them to its own names.
 */
enum Category: string
{
    case Necessary = 'necessary';
    case Preferences = 'preferences';
    case Analytics = 'analytics';
    case Marketing = 'marketing';

    /**
     * Reads "analytics", "analytics marketing", "analytics,marketing" or a
     * list. Unknown names throw, so a typo in a template is found at once.
     *
     * @param  string|list<string|self>  $categories
     * @return list<self>
     */
    public static function list(string|array $categories): array
    {
        $names = is_string($categories)
            ? preg_split('/[\s,]+/', trim($categories), -1, PREG_SPLIT_NO_EMPTY) ?: []
            : $categories;

        $list = [];

        foreach ($names as $name) {
            $category = $name instanceof self ? $name : self::tryFrom(strtolower(trim((string) $name)));

            if ($category === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown cookie category "%s". Use necessary, preferences, analytics or marketing.',
                    $name,
                ));
            }

            $list[$category->value] = $category;
        }

        return array_values($list);
    }

    /**
     * The categories a visitor can turn down: all but necessary.
     *
     * @return list<self>
     */
    public static function optional(): array
    {
        return [self::Preferences, self::Analytics, self::Marketing];
    }

    /**
     * Google Consent Mode types each category grants. Necessary covers
     * security storage, which is always granted.
     *
     * @return list<string>
     */
    public function consentTypes(): array
    {
        return match ($this) {
            self::Necessary => ['security_storage'],
            self::Preferences => ['functionality_storage', 'personalization_storage'],
            self::Analytics => ['analytics_storage'],
            self::Marketing => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        };
    }
}
