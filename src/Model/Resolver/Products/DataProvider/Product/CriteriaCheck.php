<?php


namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;


use Magento\Framework\Api\SearchCriteriaInterface;

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
     * @param \Magento\Framework\Api\Filter $filter
     * @return bool
     */
    static public function isSingleProductFilterType($filter) {
        $type = $filter->getConditionType();
        $field = $filter->getField();

        return $type === 'eq' && in_array($field, ['url_key', 'id', 'entity_id', 'sku']);
    }
}