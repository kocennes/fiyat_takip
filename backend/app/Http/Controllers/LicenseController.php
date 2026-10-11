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

use App\Http\Requests\License\CheckRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use stdClass;

/**
 * Licence claims and checks are answered locally.
 *
 * This installation never contacts an external licence server, so every
 * endpoint returns a "not available in this installation" response.
 */
class LicenseController extends BaseController
{
    /**
     * Claim a licence (not available in this installation).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->licenseUnavailable(400);
    }

    public function v5ClaimLicense(string $license_key, int $product_id = 3): JsonResponse
    {
        return $this->licenseUnavailable(400);
    }

    public function check(CheckRequest $request): Response|JsonResponse
    {
        return $this->licenseUnavailable(422);
    }

    private function licenseUnavailable(int $status): JsonResponse
    {
        return response()->json([
            'message' => ctrans('texts.bis_feature_not_available'),
            'errors' => new stdClass(),
        ], $status);
    }
}
