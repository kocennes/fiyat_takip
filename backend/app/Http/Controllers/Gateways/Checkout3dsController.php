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

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\Checkout3ds\Checkout3dsRequest;

class Checkout3dsController extends Controller
{
    public function index(Checkout3dsRequest $request, string $company_key, string $company_gateway_id, string $hash)
    {
        if (! $request->getCompany()) {
            return response()->json(['message' => ctrans('texts.bis_company_record_not_found'), 'company_key' => $company_key]);
        }

        if (! $request->getCompanyGateway()) {
            return response()->json(['message' => ctrans('texts.bis_company_gateway_record_not_found'), 'company_gateway_id' => $company_gateway_id]);
        }

        if (! $request->getPaymentHash()) {
            return response()->json(['message' => ctrans('texts.bis_payment_hash_record_not_found'), 'hash' => $hash]);
        }

        if (! $request->getClient()) {
            return response()->json(['message' => ctrans('texts.bis_client_record_not_found')]);
        }

        return $request->getCompanyGateway()
            ->driver($request->getClient())
            ->process3dsConfirmation($request);
    }
}
