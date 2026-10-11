<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Apply the BISAVUNMA Turkish regional defaults (Turkish - Turkey,
     * Europe/Istanbul, d.m.Y, Turkey, 24-hour clock) to existing companies.
     *
     * A setting is only changed while it still holds the upstream default
     * value, so anything a company configured itself is left untouched.
     * The currency is never changed.
     */
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->eachById(function ($company) {
            $settings = json_decode($company->settings ?: '', false);

            if (! $settings instanceof \stdClass) {
                return;
            }

            $value = function (string $key) use ($settings): string {
                if (! property_exists($settings, $key) || ! is_scalar($settings->{$key})) {
                    return '';
                }

                return trim((string) $settings->{$key});
            };

            // The upstream default country (United States) brings its own
            // timezone (America/New_York) and date format (m/d/Y) with it.
            $upstream_country = in_array($value('country_id'), ['', '840'], true);

            $changed = false;

            $apply = function (string $key, string $turkish, array $upstream_values) use ($settings, $value, &$changed): void {
                if (in_array($value($key), $upstream_values, true)) {
                    $settings->{$key} = $turkish;
                    $changed = true;
                }
            };

            $apply('language_id', '25', ['', '1']); // Turkish - Turkey (tr_TR)
            $apply('timezone_id', '65', $upstream_country ? ['', '1', '15'] : ['', '1']); // Europe/Istanbul
            $apply('date_format_id', '11', $upstream_country ? ['', '1', '10'] : ['', '1']); // d.m.Y (DD.MM.YYYY)
            $apply('country_id', '792', ['', '840']); // Turkey

            if (! property_exists($settings, 'military_time') || ! $settings->military_time) {
                $settings->military_time = true; // 24-hour clock
                $changed = true;
            }

            if ($changed) {
                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['settings' => json_encode($settings)]);
            }
        });
    }
};
