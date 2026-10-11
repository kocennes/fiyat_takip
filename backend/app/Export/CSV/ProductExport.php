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

namespace App\Export\CSV;

use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Export\Decorators\ProductDecorator;
use App\Transformers\ProductTransformer;
use Illuminate\Database\Eloquent\Builder;

class ProductExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'created_at';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new ProductTransformer();
        $this->decorator = new Decorator();

        /* BISAVUNMA product pricing: currency and manual exchange rate columns, right after the price. */
        $product_report_keys = [];

        foreach ($this->product_report_keys as $key => $value) {
            $product_report_keys[$key] = $value;

            if ($key === 'price') {
                $product_report_keys['currency_id'] = 'currency_id';
                $product_report_keys['exchange_rate'] = 'exchange_rate';
            }
        }

        $this->product_report_keys = $product_report_keys;
    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $this->streamQuery($query)
                ->map(function ($resource) {

                    /** @var \App\Models\Product $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->product_report_keys);
        }

        $query = Product::query()
                        ->with('tags')
                        ->withTrashed()
                        ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) { //@phpstan-ignore-line
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'products');
        $query = $this->addTagFilter($query);
        $query = $this->filterByUserPermissions($query);

        if ($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::fromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $this->streamQuery($query)
              ->each(function ($entity) {

                  /** @var \App\Models\Product $entity */
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    protected function buildRow(Product $product): array
    {
        $product->setRelation('company', $this->company);

        $transformed_entity = $this->entity_transformer->transform($product);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->product_report_keys);

            if ($key == 'currency_id') {
                $entity[$keyval] = (new ProductDecorator())->currency_id($product);
            } elseif ($key == 'exchange_rate') {
                $entity[$keyval] = $this->formatExchangeRate($product);
            } elseif (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $product);
            }
        }

        return $this->convertFloats($entity);
    }

    /**
     * The manual exchange rate (1 unit of the product currency = rate in the
     * company currency) with up to 6 decimals in the company number format,
     * returned as a string so the 2-decimal money formatting does not round it.
     */
    private function formatExchangeRate(Product $product): string
    {
        $decimal_separator = (string) ($this->company->currency()->decimal_separator ?? '.');

        $rate = number_format($product->companyExchangeRate(), 6, '.', '');
        $rate = rtrim(rtrim($rate, '0'), '.');

        return str_replace('.', $decimal_separator !== '' ? $decimal_separator : '.', $rate);
    }

}
