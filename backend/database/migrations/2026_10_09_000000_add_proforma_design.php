<?php

use App\Models\Design;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration {
    /**
     * Add the proforma layout to existing Invoice Ninja installations.
     */
    public function up(): void
    {
        if (Design::count() === 0 || Design::where('name', 'Proforma')->exists()) {
            return;
        }

        $design = new Design();
        $design->name = 'Proforma';
        $design->is_custom = false;
        $design->design = '';
        $design->is_active = true;
        $design->save();

        Artisan::call('ninja:design-update');
    }
};
