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

namespace App\Factory;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanyDefaults\TurkishDocumentDefaults;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Design;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class CompanyFactory
{
    use MakesHash;

    /**
     * @param int $account_id
     * @return Company
     */
    public function create(int $account_id): Company
    {
        $company = new Company();
        $company->account_id = $account_id;
        $company->company_key = $this->createHash();
        $company->settings = CompanySettings::defaults();

        // BISAVUNMA: new companies print invoices, quotes and credits with the Proforma design.
        if ($proforma = Design::proformaHashedId()) {
            $company->settings->invoice_design_id = $proforma;
            $company->settings->quote_design_id = $proforma;
            $company->settings->credit_design_id = $proforma;
        }
        $company->db = config('database.default');
        // BISAVUNMA: IBAN, MERSİS No and Ticaret Sicil No, printed on the proforma.
        $company->custom_fields = (object) TurkishDocumentDefaults::COMPANY_CUSTOM_FIELDS;
        $company->first_day_of_week = TurkishDocumentDefaults::FIRST_DAY_OF_WEEK;
        $company->use_comma_as_decimal_place = TurkishDocumentDefaults::USE_COMMA_AS_DECIMAL_PLACE;
        $company->client_registration_fields = ClientRegistrationFields::generate();

        if (Ninja::isHosted()) {
            $company->subdomain = MultiDB::randomSubdomainGenerator();
        } else {
            $company->subdomain = '';
        }

        $company->enabled_modules = config('ninja.enabled_modules'); //32767;//8191; //4095
        $company->default_password_timeout = 1800000;
        $company->markdown_email_enabled = true;
        $company->markdown_enabled = false;
        $company->tax_data = new TaxModel();
        $company->first_month_of_year = '1';
        $company->smtp_encryption = 'tls';
        $company->smtp_host = '';
        $company->smtp_local_domain = '';
        $company->smtp_password = '';
        $company->smtp_port = 0;
        $company->smtp_username = '';
        $company->smtp_verify_peer = true;

        return $company;
    }
}
