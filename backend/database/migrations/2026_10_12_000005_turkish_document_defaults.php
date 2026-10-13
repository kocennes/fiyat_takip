<?php

use App\DataMapper\CompanyDefaults\TurkishDocumentDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Bring existing companies to the BISAVUNMA Turkish document defaults:
     *
     * - 30-day quote validity, Turkish quote / proforma terms and the KVKK
     *   notice of the client portal, where these are still empty;
     * - clients accept the quote terms when they approve a quote online;
     * - company fields for IBAN, MERSİS No and Ticaret Sicil No (only the
     *   custom field slots that are still unused);
     * - the week starts on Monday unless another day was chosen;
     * - amounts are typed with a decimal comma (1.234,56);
     * - the KDV exemption rates (13/f, 11/1-a) next to KDV 20 / 10 / 1 / 0;
     * - the client's tax office (Vergi Dairesi) printed next to the tax number.
     *
     * Nothing a company configured itself is overwritten and nothing is deleted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $now = now();

        DB::table('companies')->orderBy('id')->eachById(function ($company) use ($now): void {
            $columns = [];

            $settings = json_decode($company->settings ?: '', false);

            if ($settings instanceof \stdClass && $this->applySettings($settings)) {
                $columns['settings'] = json_encode($settings);
            }

            $custom_fields = json_decode($company->custom_fields ?: '', false);
            $custom_fields = $custom_fields instanceof \stdClass ? $custom_fields : new \stdClass();
            $custom_fields_changed = false;

            foreach (TurkishDocumentDefaults::COMPANY_CUSTOM_FIELDS as $field => $label) {
                if (trim((string) ($custom_fields->{$field} ?? '')) === '') {
                    $custom_fields->{$field} = $label;
                    $custom_fields_changed = true;
                }
            }

            if ($custom_fields_changed) {
                $columns['custom_fields'] = json_encode($custom_fields);
            }

            if (in_array((string) ($company->first_day_of_week ?? ''), ['', '0'], true)) {
                $columns['first_day_of_week'] = TurkishDocumentDefaults::FIRST_DAY_OF_WEEK;
            }

            if (Schema::hasColumn('companies', 'use_comma_as_decimal_place') && ! $company->use_comma_as_decimal_place) {
                $columns['use_comma_as_decimal_place'] = TurkishDocumentDefaults::USE_COMMA_AS_DECIMAL_PLACE;
            }

            if ($columns !== []) {
                DB::table('companies')->where('id', $company->id)->update($columns);
            }

            if (Schema::hasTable('tax_rates')) {
                $this->ensureTaxRates((int) $company->id, $now);
            }
        });
    }

    private function applySettings(\stdClass $settings): bool
    {
        $changed = false;

        foreach (TurkishDocumentDefaults::settings() as $key => $value) {
            if (trim((string) ($settings->{$key} ?? '')) === '') {
                $settings->{$key} = $value;
                $changed = true;
            }
        }

        // Upstream default is off; clients now accept the quote terms when approving online.
        if (empty($settings->show_accept_quote_terms)) {
            $settings->show_accept_quote_terms = true;
            $changed = true;
        }

        // Customised PDF client details get the tax office before the tax number.
        $details = $settings->pdf_variables->client_details ?? null;

        if (is_array($details) && ! in_array('$client.id_number', $details, true)) {
            $position = array_search('$client.vat_number', $details, true);
            array_splice($details, $position === false ? count($details) : $position, 0, ['$client.id_number']);
            $settings->pdf_variables->client_details = $details;
            $changed = true;
        }

        return $changed;
    }

    private function ensureTaxRates(int $company_id, \DateTimeInterface $now): void
    {
        $existing = DB::table('tax_rates')
            ->where('company_id', $company_id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();

        $user_id = null;

        foreach (TurkishDocumentDefaults::TAX_RATES as $rate) {
            if ($rate['name'] === 'KDV' || in_array(mb_strtolower($rate['name']), $existing, true)) {
                continue; // KDV 20 / 10 / 1 / 0 are kept up to date by 2026_10_11_000001_turkish_kdv_rules.
            }

            $user_id ??= DB::table('company_user')
                ->where('company_id', $company_id)
                ->orderByDesc('is_owner')
                ->orderBy('id')
                ->value('user_id');

            DB::table('tax_rates')->insert([
                'company_id' => $company_id,
                'user_id' => $user_id,
                'name' => $rate['name'],
                'rate' => $rate['rate'],
                'is_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
