<?php


namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;


use Magento\Framework\Api\SearchCriteriaInterface;

class CriteriaCheck
{
    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return bool
     */
    static public function isSingleProductFilter(SearchCriteriaInterface$searchCriteria)
    {
        $singleProduct = false;
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();
            $type = $filters[0]->getConditionType();
            $field = $filters[0]->getField();
            if ($type === 'eq' && $field === 'url_key') {
                $singleProduct = true;
                break;
            }
        }
        
        return $singleProduct;
    }
}