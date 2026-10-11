<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\AppLinkPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared record links for the apps — invoiceninja/flutter#144.
 *
 * https://<instance>/app/invoices/<hashed id>?company=<hashed id>. Where the
 * app's manifest claims the host — only the hosted one, since Android and Apple
 * need build-time literals — the OS opens the app; everywhere else the browser
 * loads {@see self::bridge()}.
 *
 * The browser fallback goes to whichever web client the instance serves — see
 * {@see self::usesFlutterWebClient()}.
 */
class AppLinksController extends Controller
{
    /**
     * Routed rather than a file in public/ for the same reason as the Apple
     * document below. Unset fingerprints leave valid JSON that verifies nothing.
     */
    public function assetLinks(): JsonResponse
    {
        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('ninja.app_links.android_package'),
                    'sha256_cert_fingerprints' => $this->configList('ninja.app_links.android_fingerprints'),
                ],
            ],
        ]);
    }

    /**
     * Must be served as application/json, which an extension-less file is not,
     * and Apple does not follow redirects to it. Scoped to /app/* deliberately:
     * a wildcard would make the admin app swallow every client-portal and
     * payment link.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => $this->configList('ninja.app_links.apple_app_ids'),
                        'components' => [
                            [
                                '/' => '/app/*',
                                'comment' => 'Shared record links',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Where a shared link lands when the OS did not claim it — every
     * self-hosted domain — so the only thing that can offer those users the app.
     *
     * $path is attacker-supplied and ends up in hrefs: route-constrained,
     * collapsed here, escaped by Blade.
     */
    public function bridge(Request $request, string $path = '')
    {
        $path = trim(preg_replace('#/+#', '/', $path), '/');

        // This installation has no mobile app, so a shared link never offers to
        // open one: it goes straight to the web client.
        return redirect()
            ->away($this->webUrl($path, $request->query()))
            ->header('X-Robots-Tag', 'noindex');
    }

    private function webUrl(string $path, array $query): string
    {
        $suffix = empty($query) ? '' : '?'.http_build_query($query);

        // Untranslated: a Flutter web client built from the apps shares their
        // route table. The bundled admin-portal v1 opens at its main screen.
        if ($this->usesFlutterWebClient()) {
            $base = rtrim((string) config('ninja.app_url') ?: url('/'), '/');

            return $base.'/#/'.$path.$suffix;
        }

        $base = rtrim((string) config('ninja.react_url') ?: url('/'), '/');

        return $base.'/#/'.AppLinkPath::forWebClient($path).$suffix;
    }

    /**
     * Whether `/` serves the Flutter web client rather than React — the same
     * read {@see BaseController::flutterRoute()} makes.
     *
     * Self-host only: hosted serves the two from different hosts and never
     * checks the flag. Wrapped because the bridge has to render before setup
     * has run.
     */
    private function usesFlutterWebClient(): bool
    {
        // `/` always serves the React UI, see BaseController::flutterRoute().
        return false;
    }

    private function configList(string $key): array
    {
        return collect(explode(',', (string) config($key)))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->values()
            ->all();
    }
}
