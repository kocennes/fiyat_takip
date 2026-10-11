<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Utils\Traits\Invoice\ActionsInvoice;
use App\Utils\Traits\MakesHash;

class ActionInvoiceRequest extends Request
{
    use MakesHash;
    use ActionsInvoice;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    // private $error_msg;

    // private $invoice;

    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
        return [
            'action' => ['required'],
        ];
    }

    public function withValidator($validator)
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        
        $validator->after(function ($validator) {

            if ($this->action == 'delete' && ! $this->invoiceDeletable($this->invoice)) {
                $validator->errors()->add('action', ctrans('texts.bis_invoice_cannot_be_deleted'));
            } elseif ($this->action == 'cancel' && ! $this->invoiceCancellable($this->invoice)) {
                $validator->errors()->add('action', ctrans('texts.bis_invoice_cannot_be_cancelled'));
            } elseif ($this->action == 'reverse' && ! $this->invoiceReversable($this->invoice)) {
                $validator->errors()->add('action', ctrans('texts.bis_invoice_cannot_be_reversed'));
            } elseif ($this->action == 'restore' && ! $this->invoiceRestorable($this->invoice)) {
                $validator->errors()->add('action', ctrans('texts.bis_invoice_cannot_be_restored'));
            } elseif ($this->action == 'mark_paid' && ! $this->invoicePayable($this->invoice)) {
                $validator->errors()->add('action', ctrans('texts.invoice_cannot_be_marked_paid'));
            }
        });

    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['action'] = $this->route('action');

        $this->replace($input);
    }

}
