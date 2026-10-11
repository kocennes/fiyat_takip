<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * KDV rates every company must have: genel %20, indirimli %10 and %1,
     * istisna %0.
     */
    private const KDV_RATES = [20, 10, 1, 0];

    /** Default tax of new line items. */
    private const DEFAULT_LINE_TAX = ['name' => 'KDV', 'rate' => 20];

    /**
     * Apply the BISAVUNMA Turkish KDV rules to existing companies:
     *
     * - KDV is charged per line item only: one line tax for items and
     *   expenses, no document-level taxes, prices KDV hariç and the
     *   automatic US/EU tax engine off. The product purchase price is shown.
     * - New line items default to KDV %20 unless the company already uses one
     *   of the KDV rates below as its default.
     * - The KDV rates 20 / 10 / 1 / 0 exist; other active (non-KDV) tax rates
     *   are archived. Nothing is deleted.
     * - PDF totals list the line taxes per rate (KDV 20%, KDV 10% …).
     * - Foreign e-invoicing (Peppol, Verifactu, ZUGFeRD …) is switched off;
     *   its settings screen is no longer offered.
     *
     * Existing documents are never touched: they keep their taxes and amounts.
     */
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('tax_rates')) {
            return;
        }

        $now = now();

        DB::table('companies')->orderBy('id')->eachById(function ($company) use ($now) {
            $columns = [
                'enabled_tax_rates' => 0,
                'enabled_item_tax_rates' => 1,
                'enabled_expense_tax_rates' => 1,
                'calculate_taxes' => false,
                'enable_product_cost' => true,
            ];

            $settings = json_decode($company->settings ?: '', false);

            if ($settings instanceof \stdClass) {
                $this->applySettings($settings);

                $encoded = json_encode($settings);

                if (is_string($encoded)) {
                    $columns['settings'] = $encoded;
                }
            }

            DB::table('companies')->where('id', $company->id)->update($columns);

            $this->ensureKdvRates((int) $company->id, $now);
        });
    }

    private function applySettings(\stdClass $settings): void
    {
        $settings->inclusive_taxes = false;

        $default_name = property_exists($settings, 'tax_name1') && is_scalar($settings->tax_name1)
            ? trim((string) $settings->tax_name1)
            : '';
        $default_rate = property_exists($settings, 'tax_rate1') && is_numeric($settings->tax_rate1)
            ? (float) $settings->tax_rate1
            : -1.0;

        if (! $this->isKdv($default_name) || ! $this->hasRate(self::KDV_RATES, $default_rate)) {
            $settings->tax_name1 = self::DEFAULT_LINE_TAX['name'];
            $settings->tax_rate1 = self::DEFAULT_LINE_TAX['rate'];
        }

        $settings->enable_e_invoice = false;

        if (isset($settings->e_invoice_type) && in_array($settings->e_invoice_type, ['PEPPOL', 'VERIFACTU'], true)) {
            $settings->e_invoice_type = 'EN16931';
        }

        // Without customised PDF variables the defaults (which list the line
        // taxes) apply; a customised, non-empty totals list gets $line_taxes back.
        if (! isset($settings->pdf_variables) || ! is_object($settings->pdf_variables)
            || ! isset($settings->pdf_variables->total_columns)) {
            return;
        }

        $columns = array_values(array_filter(
            (array) $settings->pdf_variables->total_columns,
            fn ($column) => is_string($column)
        ));

        if ($columns === [] || in_array('$line_taxes', $columns, true)) {
            return;
        }

        $total_taxes = array_search('$total_taxes', $columns, true);
        $total = array_search('$total', $columns, true);

        if ($total_taxes !== false) {
            array_splice($columns, $total_taxes + 1, 0, ['$line_taxes']);
        } elseif ($total !== false) {
            array_splice($columns, $total, 0, ['$line_taxes']);
        } else {
            $columns[] = '$line_taxes';
        }

        $settings->pdf_variables->total_columns = $columns;
    }

    private function ensureKdvRates(int $company_id, \DateTimeInterface $now): void
    {
        // Deleted rates (is_deleted) are left alone; archived ones have deleted_at set.
        $rates = DB::table('tax_rates')
            ->where('company_id', $company_id)
            ->where('is_deleted', false)
            ->orderBy('id')
            ->get();

        $active_kdv_rates = [];

        foreach ($rates as $rate) {
            if ($rate->deleted_at !== null) {
                continue;
            }

            if ($this->isKdv((string) $rate->name)) {
                $active_kdv_rates[] = (float) $rate->rate;

                continue;
            }

            DB::table('tax_rates')
                ->where('id', $rate->id)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }

        $user_id = null;

        foreach (self::KDV_RATES as $kdv) {
            if ($this->hasRate($active_kdv_rates, $kdv)) {
                continue;
            }

            // Prefer restoring an archived "KDV" rate over adding a duplicate.
            $archived = $rates->first(fn ($rate) => $rate->deleted_at !== null
                && strcasecmp(trim((string) $rate->name), self::DEFAULT_LINE_TAX['name']) === 0
                && $this->hasRate([(float) $rate->rate], $kdv));

            if ($archived) {
                DB::table('tax_rates')
                    ->where('id', $archived->id)
                    ->update(['deleted_at' => null, 'updated_at' => $now]);

                continue;
            }

            $user_id ??= $this->ownerId($company_id);

            DB::table('tax_rates')->insert([
                'company_id' => $company_id,
                'user_id' => $user_id,
                'name' => self::DEFAULT_LINE_TAX['name'],
                'rate' => $kdv,
                'is_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function isKdv(string $name): bool
    {
        return stripos($name, 'kdv') !== false
            || preg_match('/katma\s+de[gğ]er/iu', $name) === 1;
    }

    /**
     * @param array<int, int|float> $rates
     */
    private function hasRate(array $rates, float $kdv): bool
    {
        foreach ($rates as $rate) {
            if (abs($rate - $kdv) < 0.000001) {
                return true;
            }
        }

        return false;
    }

    private function ownerId(int $company_id): ?int
    {
        $user_id = DB::table('company_user')
            ->where('company_id', $company_id)
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->value('user_id');

        return $user_id ? (int) $user_id : null;
    }
};
