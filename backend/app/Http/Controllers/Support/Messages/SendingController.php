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
namespace App\Http\Controllers\Support\Messages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SendingController extends Controller
{
    /**
     * Support messages to an external vendor are not available in this
     * installation: nothing is sent anywhere, users contact their system
     * administrator instead.
     *
     * @OA\Post(
     *      path="/api/v1/support/messages/send",
     *      operationId="supportMessage",
     *      tags={"support"},
     *      summary="Support messages are not available in this installation",
     *      description="Always answers that the feature is not available; no message is sent",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *         description="The message",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string",
     *                 @OA\Property(
     *                     property="message",
     *                     description="The support message",
     *                     type="string",
     *                 ),
     *             )
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\MediaType(
     *             mediaType="application/json",
     *               @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="string",
     *                     description="Server response",
     *                     example=true,
     *                 ),
     *             )
     *          )
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => ctrans('texts.bis_support_contact_admin'),
        ], 403);
    }
}
