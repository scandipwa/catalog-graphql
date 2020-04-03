<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Api\Filter;

/**
 * Product filter allows to filter products collection using custom defined filters from search criteria.
 */
class ProductIdFilter implements CustomFilterInterface
{
    /**
     * @param Filter $filter
     * @param AbstractDb $collection
     * @return bool
     */
    public function apply(Filter $filter, AbstractDb $collection)
    {
        $attributeValue = $filter->getValue();
        $conditionType = $filter->getConditionType();

        $collection->getSelect()
            ->where($collection->getConnection()->prepareSqlCondition(
                'e.entity_id',
                [$conditionType => $attributeValue]
            ));

        return true;
    }
}
