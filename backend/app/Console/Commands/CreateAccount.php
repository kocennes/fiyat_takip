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

namespace App\Console\Commands;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanyDefaults\TurkishDocumentDefaults;
use App\DataMapper\CompanySettings;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Design;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAccount extends Command
{
    use MakesHash;
    use GeneratesCounter;

    /**
     * @var string
     */
    protected $description = 'Create Single Account';

    /**
     * @var string
     */
    protected $signature = 'ninja:create-account {--email=} {--password=}';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(date('r') . ' Create Single Account...');

        $this->createAccount();
    }

    private function createAccount()
    {
        $settings = CompanySettings::defaults();

        $settings->name = "BISAVUNMA Fiyat Takip";
        $settings->currency_id = '41'; // Turkish Lira (TRY)
        $settings->language_id = '25'; // Turkish - Turkey (tr_TR)
        $settings->timezone_id = '65'; // Europe/Istanbul
        $settings->date_format_id = '11'; // d.m.Y (DD.MM.YYYY)
        $settings->country_id = '792'; // Turkey
        $settings->military_time = true; // 24-hour clock

        if ($proforma = Design::proformaHashedId()) {
            $settings->invoice_design_id = $proforma;
            $settings->quote_design_id = $proforma;
            $settings->credit_design_id = $proforma;
        }
        // CompanySettings::defaults() already makes new line items default to
        // KDV %20 with prices entered KDV hariç.

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'portal_domain' => config('ninja.app_url'),
            'portal_mode' => 'domain',
            'settings' => $settings,
            // KDV per line item only: no document-level taxes, no automatic
            // US/EU tax engine; the product purchase price is always shown.
            'enabled_tax_rates' => 0,
            'enabled_item_tax_rates' => 1,
            'enabled_expense_tax_rates' => 1,
            'calculate_taxes' => false,
            'enable_product_cost' => true,
            // IBAN, MERSİS No and Ticaret Sicil No, printed on the proforma; week starts on Monday.
            'custom_fields' => (object) TurkishDocumentDefaults::COMPANY_CUSTOM_FIELDS,
            'first_day_of_week' => TurkishDocumentDefaults::FIRST_DAY_OF_WEEK,
            'use_comma_as_decimal_place' => TurkishDocumentDefaults::USE_COMMA_AS_DECIMAL_PLACE,
        ]);

        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->save();

        $account->default_company_id = $company->id;
        $account->set_react_as_default_ap = true;
        $account->save();

        $email = $this->option('email') ?? 'admin@example.com';
        $password = $this->option('password') ?? 'changeme!';

        $locale = $company->locale();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $email,
            'password' => Hash::make($password),
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email_verified_at' => now(),
            'first_name'        => ctrans('texts.new', [], $locale),
            'last_name'         => ctrans('texts.user', [], $locale),
            'phone'             => '',
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'User Token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        // Turkish KDV rates 20 / 10 / 1 / 0 and the KDV exemptions (CountryDefaults for Turkey).
        $company->service()->localizeCompany($user);

        (new CreateCompanyPaymentTerms($company, $user))->handle();
        (new CreateCompanyTaskStatuses($company, $user))->handle();
        (new VersionCheck())->handle();

    }

}
