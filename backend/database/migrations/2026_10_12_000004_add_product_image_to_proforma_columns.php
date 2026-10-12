<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /** Add the product-image column to existing BISAVUNMA PDF settings. */
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->eachById(function ($company): void {
            $settings = json_decode($company->settings ?: '{}');
            if (! is_object($settings)) {
                $settings = new stdClass();
            }

            $pdfVariables = is_object($settings->pdf_variables ?? null)
                ? $settings->pdf_variables
                : new stdClass();
            $changed = false;

            foreach (['product_columns', 'product_quote_columns'] as $columnSet) {
                $columns = array_values((array) ($pdfVariables->{$columnSet} ?? []));
                if (! in_array('$product.image', $columns, true)) {
                    array_unshift($columns, '$product.image');
                    $pdfVariables->{$columnSet} = $columns;
                    $changed = true;
                }
            }

            if ($changed) {
                $settings->pdf_variables = $pdfVariables;
                DB::table('companies')->where('id', $company->id)->update([
                    'settings' => json_encode($settings),
                ]);
            }
        });
    }
};
