<?php


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
    public static function isSingleProductFilter(SearchCriteriaInterface $searchCriteria)
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
     * @param Filter $filter
     * @return bool
     */
    public static function isSingleProductFilterType($filter) {
        $type = $filter->getConditionType();
        $field = $filter->getField();

        return $type === 'eq' && in_array($field, ['url_key', 'id', 'entity_id', 'sku']);
    }
}
