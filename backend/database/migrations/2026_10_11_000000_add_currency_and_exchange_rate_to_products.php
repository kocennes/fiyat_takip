<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * BISAVUNMA product pricing: a product carries the currency of its
     * purchase price (cost / alış fiyatı) and sale price (price / satış
     * fiyatı) together with a manually entered exchange rate.
     *
     * - products.currency_id   currency of BOTH cost and price;
     *                          NULL or '' = the company currency (TRY).
     * - products.exchange_rate 1 unit of the product currency =
     *                          exchange_rate units of the company currency
     *                          (TRY). Always 1 for company-currency
     *                          products; existing products get 1.
     *
     * The purchase price must always be visible, so the company "show
     * product cost" setting (companies.enable_product_cost) is switched on
     * for existing companies (new companies get it from the Company model
     * defaults).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'currency_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('currency_id', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('products', 'exchange_rate')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('exchange_rate', 20, 6)->default(1);
            });
        }

        if (Schema::hasColumn('companies', 'enable_product_cost')) {
            DB::table('companies')
                ->where('enable_product_cost', false)
                ->update(['enable_product_cost' => true]);
        }
    }

    /**
     * Drops the two product columns again. The companies' enable_product_cost
     * values are left as they are.
     */
    public function down(): void
    {
        foreach (['exchange_rate', 'currency_id'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
