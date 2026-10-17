<?php

namespace NineteenNinetyFour\CookieConnect\Core\Settings;

/**
 * Google Tag Manager, Google Analytics 4 and Consent Mode, for one site.
 */
final class GoogleTags
{
    public const GTM_PATTERN = '/^GTM-[A-Z0-9]{4,12}$/';

    public const GA4_PATTERN = '/^G-[A-Z0-9]{4,15}$/';

    /** Where Consent Mode denies by default: everywhere, or only where the law asks (EEA, UK, Switzerland). */
    public const REGIONS = ['everywhere', 'eea'];

    /** The EEA, the UK and Switzerland, as ISO 3166 codes. */
    public const EEA = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV',
        'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB', 'CH',
    ];

    public function __construct(
        public readonly ?string $gtmId = null,
        public readonly ?string $ga4Id = null,
        public readonly bool $consentMode = true,
        public readonly string $mode = 'advanced',
        public readonly string $regions = 'everywhere',
        public readonly bool $urlPassthrough = false,
        public readonly bool $adsDataRedaction = true,
        public readonly bool $gtmSetsDefaults = false,
        public readonly ?int $waitForUpdate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = fn (string $key) => ($value = strtoupper(trim((string) ($data[$key] ?? '')))) === '' ? null : $value;
        $wait = $data['waitForUpdate'] ?? null;

        return new self(
            gtmId: $id('gtmId'),
            ga4Id: $id('ga4Id'),
            consentMode: filter_var($data['consentMode'] ?? true, FILTER_VALIDATE_BOOLEAN),
            mode: in_array($data['mode'] ?? null, ['advanced', 'basic'], true) ? $data['mode'] : 'advanced',
            regions: in_array($data['regions'] ?? null, self::REGIONS, true) ? $data['regions'] : 'everywhere',
            urlPassthrough: filter_var($data['urlPassthrough'] ?? false, FILTER_VALIDATE_BOOLEAN),
            adsDataRedaction: filter_var($data['adsDataRedaction'] ?? true, FILTER_VALIDATE_BOOLEAN),
            gtmSetsDefaults: filter_var($data['gtmSetsDefaults'] ?? false, FILTER_VALIDATE_BOOLEAN),
            waitForUpdate: $wait === null || $wait === '' ? null : max(0, (int) $wait),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gtmId' => $this->gtmId,
            'ga4Id' => $this->ga4Id,
            'consentMode' => $this->consentMode,
            'mode' => $this->mode,
            'regions' => $this->regions,
            'urlPassthrough' => $this->urlPassthrough,
            'adsDataRedaction' => $this->adsDataRedaction,
            'gtmSetsDefaults' => $this->gtmSetsDefaults,
            'waitForUpdate' => $this->waitForUpdate,
        ];
    }

    /**
     * Pulls the ID out of a pasted GTM or gtag snippet.
     */
    public static function extractGtm(string $value): string
    {
        $value = strtoupper(trim($value));

        return preg_match('/GTM-[A-Z0-9]{4,12}/', $value, $m) ? $m[0] : $value;
    }

    public static function extractGa4(string $value): string
    {
        $value = strtoupper(trim($value));

        return preg_match('/\bG-[A-Z0-9]{4,15}\b/', $value, $m) ? $m[0] : $value;
    }
}
