<?php

/**
 * BISAVUNMA Fiyat Takip - client / vendor portal branding helpers.
 */

namespace App\Http\ViewComposers;

/**
 * Class PortalBranding.
 *
 * Central place for the product branding used by the portal views:
 * the product name, the logo that is shown when a company has not
 * uploaded a logo of its own and the legal links (which must never
 * point to the upstream vendor's web site).
 */
class PortalBranding
{
    public const PRODUCT_NAME = 'BISAVUNMA Fiyat Takip';

    /**
     * The BISAVUNMA logo (for light backgrounds).
     */
    public static function defaultLogo(): string
    {
        return asset('brand/bisavunma-logo.png');
    }

    /**
     * The company's own logo when one is uploaded, otherwise the BISAVUNMA logo.
     *
     * @param  mixed $company  \App\Models\Company|null
     * @param  mixed $settings merged settings object (optional)
     */
    public static function logo($company = null, $settings = null): string
    {
        if (! $company) {
            return self::defaultLogo();
        }

        $settings = $settings ?: $company->settings;

        if ($settings && strlen((string) ($settings->company_logo ?? '')) >= 1) {
            return $company->present()->logo($settings);
        }

        return self::defaultLogo();
    }

    /**
     * True when the URL points to the upstream vendor's web sites.
     */
    public static function isUpstreamUrl(?string $url): bool
    {
        return is_string($url) && preg_match('/invoiceninja\.(com|org|net)|invoicing\.co/i', $url) === 1;
    }

    /**
     * The configured privacy policy URL, or null when none (or only the upstream default) is configured.
     */
    public static function privacyPolicyUrl(): ?string
    {
        $url = config('ninja.privacy_policy_url.hosted');

        return filled($url) && ! self::isUpstreamUrl($url) ? $url : null;
    }

    /**
     * The configured terms of service URL, or null when none (or only the upstream default) is configured.
     */
    public static function termsOfServiceUrl(): ?string
    {
        $url = config('ninja.terms_of_service_url.hosted');

        return filled($url) && ! self::isUpstreamUrl($url) ? $url : null;
    }
}
