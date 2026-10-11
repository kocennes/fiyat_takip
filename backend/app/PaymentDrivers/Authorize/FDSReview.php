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

namespace App\PaymentDrivers\Authorize;

use App\Libraries\MultiDB;
use App\Models\PaymentHash;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/*Multi Mailer implemented*/

class FDSReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1; //number of retries
    public $deleteWhenMissingModels = true;

    public function __construct(private string $transaction_reference, private PaymentHash $payment_hash, private string $db) {}

    public function handle()
    {

        MultiDB::setDB($this->db);

        $company = $this->payment_hash->fee_invoice->company;

        App::setLocale($company->getLocale());

        $invoices_string = \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()) ?: '';

        $body = implode("\n\n\n", [
            ctrans('texts.bis_pd_fds_review_held', ['reference' => $this->transaction_reference]),
            ctrans('texts.bis_pd_fds_review_marked_paid', ['invoices' => $invoices_string]),
            ctrans('texts.bis_pd_fds_review_action'),
            ctrans('texts.bis_pd_fds_review_cancel'),
        ]);

        $mo = new EmailObject();
        $mo->subject = ctrans('texts.bis_pd_fds_review_subject', ['reference' => $this->transaction_reference]);
        $mo->body = nl2br($body);
        $mo->text_body = $body;
        $mo->company_key = $company->company_key;
        $mo->html_template = 'email.template.generic';
        $mo->to = [new Address($company->owner()->email, $company->owner()->present()->name())];

        Email::dispatch($mo, $company);

    }

}
