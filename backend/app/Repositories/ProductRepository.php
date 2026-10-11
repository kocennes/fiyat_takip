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

namespace App\Repositories;

use App\Models\Product;
use App\Utils\Traits\SavesDocuments;

class ProductRepository extends BaseRepository
{
    use SavesDocuments;

    /**
     * @param array $data
     * @param Product $product
     * @return Product|null
     */
    public function save(array $data, Product $product): ?Product
    {
        $tag_ids = $this->resolveTagIdsForSync($data, $product);

        $product->fill($data);

        /* BISAVUNMA: cost and price are in the product currency; a company-currency product always has exchange rate 1. */
        if ($product->hasCompanyCurrency() || (float) $product->exchange_rate <= 0) {
            $product->exchange_rate = 1;
        }

        $product->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $product);
        }

        $this->syncResolvedTags($product, $tag_ids);

        return $product;
    }
}
