<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Make Turkish the standard interface and document language for this
     * installation, including accounts created before the BISAVUNMA branding.
     */
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->eachById(function ($company) {
            $settings = json_decode($company->settings ?: '{}', true) ?: [];
            $settings['language_id'] = '25'; // Turkish - Turkey

            DB::table('companies')
                ->where('id', $company->id)
                ->update(['settings' => json_encode($settings)]);
        });

        DB::table('users')->update(['language_id' => '25']);
    }
};
