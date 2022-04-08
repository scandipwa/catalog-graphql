<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Class CriteriaCheck
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product
 */
class CriteriaCheck
{
    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return bool
     */
    static public function isSingleProductFilter(SearchCriteriaInterface $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                if (self::isSingleProductFilterType($filter)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return bool
     */
    static public function isOnlySingleIdFilter(SearchCriteriaInterface $searchCriteria): bool
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                $type = $filter->getConditionType();
                $field = $filter->getField();

                // skippable filters that are always present
                if (in_array($field, ['customer_group_id', 'visibility'])) {
                    continue;
                }

                if ($type !== 'eq' || $field !== 'id') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Filter $filter
     * @return bool
     */
    static public function isSingleProductFilterType($filter) {
        $type = $filter->getConditionType();
        $field = $filter->getField();

        return $type === 'eq' && in_array($field, ['url_key', 'id', 'entity_id', 'sku']);
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Filter|null
     */
    static public function getVisibilityFilter(SearchCriteriaInterface $searchCriteria): ?Filter
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                if ($filter->getField() === 'visibility') {
                    return $filter;
                }
            }
        }

        return null;
    }
}
