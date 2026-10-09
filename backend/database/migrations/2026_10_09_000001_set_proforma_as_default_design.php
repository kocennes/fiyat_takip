<?php

use App\Models\Design;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    use MakesHash;

    /**
     * Make the Bisavunma Proforma design the default for future invoices and
     * quotations. Invoice Ninja stores the selected design as a hashed ID in
     * the company's JSON settings.
     */
    public function up(): void
    {
        $design = Design::where('name', 'Proforma')->first();

        if (! $design) {
            return;
        }

        $designId = $this->encodePrimaryKey($design->id);

        DB::table('companies')->orderBy('id')->eachById(function ($company) use ($designId) {
            $settings = json_decode($company->settings ?: '{}', true) ?: [];
            $settings['invoice_design_id'] = $designId;
            $settings['quote_design_id'] = $designId;

            DB::table('companies')
                ->where('id', $company->id)
                ->update(['settings' => json_encode($settings)]);
        });

        DB::table('invoices')->update(['design_id' => $design->id]);
        DB::table('quotes')->update(['design_id' => $design->id]);
    }
};
