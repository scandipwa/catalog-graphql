<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Yefim Butrameev <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;

/**
 * Category filter allows to filter products collection using custom defined filters from search criteria.
 */
class CustomerGroupFilter implements CustomFilterInterface
{
    /**
     * Apply filter by custom field to product collection.
     *
     * @param Filter $filter
     * @param AbstractDb $collection
     * @return bool Whether the filter is applied
     * @throws LocalizedException
     */
    public function apply(Filter $filter, AbstractDb $collection)
    {
        $conditionType = $filter->getConditionType();
        $rawFilterField = $filter->getField();

        if ($conditionType !== 'eq') {
            throw new LocalizedException(__($rawFilterField . " only supports 'eq' condition type."));
        }

        $collection->addPriceData($filter->getValue());

        return true;
    }
}
