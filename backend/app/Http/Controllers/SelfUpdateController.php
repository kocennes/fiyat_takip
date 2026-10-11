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

use App\Http\Requests\SelfUpdate\SelfUpdateRequest;
use App\Utils\Ninja;

/**
 * In-app self-update is disabled: it would download release archives from an
 * external project. This installation is updated by its system administrator.
 */
class SelfUpdateController extends BaseController
{
    public function __construct() {}

    public function update(SelfUpdateRequest $request)
    {
        return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
    }

    /**
     * Reports the installed version as the latest one, so no update is ever
     * offered and no external version server is contacted.
     */
    public function checkVersion()
    {
        if (Ninja::isHosted()) {
            return '5.10.SaaS';
        }

        return trim((string) config('ninja.app_version'));
    }
}
