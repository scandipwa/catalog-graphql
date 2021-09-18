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
 * Adds passed in attributes to product collection results
 *
 * {@inheritdoc}
 */
class AttributeProcessor implements CollectionProcessorInterface
{
    const ATTRIBUTES_FIELD = 'attributes';

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ContextInterface $context = null
    ): Collection {
        // returning individual addAttributeToSelect calls
        // while each of these runs a mysql query
        // it is still faster than adding all attributes to select
        // since that adds default+store-specific joins for each attribute in collection afterLoad
        // previous addAttibuteToSelect('*') simply transferred the bulk of load to a different point in time
        foreach ($attributeNames as $name) {
            if ($name !== self::ATTRIBUTES_FIELD) {
                $collection->addAttributeToSelect($name);

                continue;
            }
        }

        return $collection;
    }
}
