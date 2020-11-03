<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Adds price data to product collection
 *
 * {@inheritdoc}
 */
class PriceProcessor implements CollectionProcessorInterface
{
    const PRICE_FIELD = 'price_range';

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ContextInterface $context = null
    ): Collection {
        // add tax percent, no-matter what
        $collection->addTaxPercents();

        if (in_array(self::PRICE_FIELD, $attributeNames, true)) {
            /** @var $collection Collection */
            $collection->addPriceData();
        }

        return $collection;
    }
}
