<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Turkish lira amounts are written with the symbol after the amount
     * ("1.234,56 TL"), as on Turkish invoices. CurrenciesSeeder carries the
     * same flag for new installations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('currencies') || ! Schema::hasColumn('currencies', 'swap_currency_symbol')) {
            return;
        }

        DB::table('currencies')->where('code', 'TRY')->update(['swap_currency_symbol' => true]);

        Cache::forget('currencies');
    }
};
