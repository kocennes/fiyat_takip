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

namespace App\Services\Quickbooks\Invoice;

use App\DataMapper\InvoiceSync;
use App\Models\Invoice;
use App\Services\Quickbooks\QuickbooksService;

class InvoiceCollisionPolicy
{
    public function __construct(
        private QuickbooksService $service,
        private InvoiceLookup $lookup,
    ) {
    }

    /**
     * @param  array<string, mixed>  $ninja_invoice_data
     */
    public function handlePullNumberCollision(array $ninja_invoice_data, mixed $qb_record): bool
    {
        $number = $ninja_invoice_data['number'] ?? null;

        if (empty($number)) {
            return false;
        }

        $existing = $this->lookup->findByNumber((string) $number);

        if (!$existing) {
            return false;
        }

        if (!empty($existing->sync->qb_id ?? false)) {
            nlog('QuickBooks: Skipping create — DocNumber owned by linked invoice', [
                'number' => $number,
                'existing_invoice_id' => $existing->id,
                'existing_qb_id' => $existing->sync->qb_id ?? null,
                'incoming_qb_id' => $ninja_invoice_data['id'] ?? null,
            ]);

            return true;
        }

        $this->flagNumberCollision($existing, $qb_record);

        nlog('QuickBooks: Skipping create — DocNumber collision flagged on existing invoice', [
            'number' => $number,
            'existing_invoice_id' => $existing->id,
            'qb_status' => data_get($existing->sync, 'qb_status'),
            'incoming_qb_id' => $ninja_invoice_data['id'] ?? null,
        ]);

        return true;
    }

    public function findQbInvoiceByDocNumber(string $doc_number, bool $fail_open = true): mixed
    {
        if ($doc_number === '') {
            return null;
        }

        try {
            $escaped = str_replace("'", "\\'", $doc_number);
            $result = $this->service->sdk()->query("select * from Invoice where DocNumber = '{$escaped}'");
        } catch (\Throwable $e) {
            if (!$fail_open) {
                throw $e;
            }

            nlog("QuickBooks: DocNumber preflight failed for '{$doc_number}', proceeding with create: {$e->getMessage()}");

            return null;
        }

        if (empty($result)) {
            return null;
        }

        if (is_array($result)) {
            return $result[0] ?? null;
        }

        return $result;
    }

    public function amountsMatch(mixed $qb_record, Invoice $invoice): bool
    {
        $qb_total = (float) data_get($qb_record, 'TotalAmt', 0);

        return abs($qb_total - (float) $invoice->amount) <= 0.01;
    }

    public function customerMatches(Invoice $invoice, mixed $qb_record): bool
    {
        $invoice->loadMissing('client');

        $ninja_customer_id = trim((string) data_get($invoice->client->sync, 'qb_id', ''));
        $qb_customer_id = $this->qbCustomerId($qb_record);

        return $ninja_customer_id !== '' && $qb_customer_id !== '' && $ninja_customer_id === $qb_customer_id;
    }

    public function qbCustomerId(mixed $qb_record): string
    {
        $ref = data_get($qb_record, 'CustomerRef.value') ?? data_get($qb_record, 'CustomerRef');

        if (is_array($ref)) {
            $ref = $ref['value'] ?? '';
        }

        if (is_object($ref)) {
            $ref = data_get($ref, 'value') ?? '';
        }

        return trim((string) $ref);
    }

    public function linkedInvoiceCheckMessage(Invoice $invoice, mixed $qb_record): ?string
    {
        if (data_get($qb_record, 'TxnStatus') === 'Voided') {
            return 'The linked QuickBooks invoice is voided.';
        }

        $qb_number = (string) data_get($qb_record, 'DocNumber', '');
        $ninja_number = (string) $invoice->number;
        $number_differs = $qb_number !== $ninja_number;
        $amount_differs = !$this->amountsMatch($qb_record, $invoice);

        if (!$number_differs && !$amount_differs) {
            return null;
        }

        $qb_total = number_format((float) data_get($qb_record, 'TotalAmt', 0), 2, '.', '');
        $ninja_total = number_format((float) $invoice->amount, 2, '.', '');

        $message = match (true) {
            $number_differs && $amount_differs
                => ctrans('texts.bis_qb_invoice_differs_both', ['qb_number' => $qb_number, 'number' => $ninja_number, 'qb_total' => $qb_total, 'total' => $ninja_total]),
            $number_differs
                => ctrans('texts.bis_qb_invoice_number_differs', ['qb_number' => $qb_number, 'number' => $ninja_number]),
            default
                => ctrans('texts.bis_qb_invoice_total_differs', ['qb_total' => $qb_total, 'total' => $ninja_total]),
        };

        return mb_substr($message, 0, 255);
    }

    public function flagNumberCollision(Invoice $invoice, mixed $qb_record): void
    {
        if (!empty($invoice->sync->qb_id ?? null)) {
            return;
        }

        $qb_id = (string) data_get($qb_record, 'Id', '');
        $doc_number = (string) data_get($qb_record, 'DocNumber', $invoice->number ?? '');
        $qb_total = number_format((float) data_get($qb_record, 'TotalAmt', 0), 2, '.', '');
        $ninja_total = number_format((float) $invoice->amount, 2, '.', '');

        $sync = $invoice->sync ?? new InvoiceSync();

        if (!$this->amountsMatch($qb_record, $invoice)) {
            $sync->markDataMismatch(
                ctrans('texts.bis_qb_number_used_different_total', ['number' => $doc_number, 'qb_total' => $qb_total, 'total' => $ninja_total])
            );
        } elseif (!$this->customerMatches($invoice, $qb_record)) {
            $sync->markDataMismatch(
                ctrans('texts.bis_qb_customer_mismatch', ['number' => $doc_number, 'qb_id' => $qb_id, 'qb_total' => $qb_total])
            );
        } else {
            $sync->markLinkable(
                ctrans('texts.bis_qb_linkable', ['number' => $doc_number, 'qb_id' => $qb_id, 'qb_total' => $qb_total])
            );
        }

        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }
}
