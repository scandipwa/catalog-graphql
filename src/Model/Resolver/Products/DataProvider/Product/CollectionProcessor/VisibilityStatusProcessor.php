<?php
/**
 * @category  ScandiPWA
 * @package   ScandiPWA_CatalogGraphQl
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\VisibilityStatusProcessor as CoreVisibilityStatusProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\GraphQl\Model\Query\ContextInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;

/**
 * Class VisibilityStatusProcessor
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor
 */
class VisibilityStatusProcessor extends CoreVisibilityStatusProcessor
{
    /**
     * Process collection to add additional joins, attributes, and clauses to a product collection.
     * Rewrite: avoids joining the visibility attribute, if the filter was already present in searchCriteria
     *
     * @param Collection $collection
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $attributeNames
     * @param ContextInterface|null $context
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ContextInterface $context = null
    ): Collection {
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');

        $visibilityFilter = CriteriaCheck::getVisibilityFilter($searchCriteria);

        if (!$visibilityFilter) {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        return $collection;
    }
}
