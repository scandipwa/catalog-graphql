<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor;

use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Data\Collection\AbstractDb;

class ExclusiveFilterProcessor extends FilterProcessor
{

    private $ignoredFilters = [];

    public function setIgnoredFilters($ignoredFilters)
    {
        $this->ignoredFilters = $ignoredFilters;

        return $this;
    }

    /**
     * Add FilterGroup to the collection
     *
     * @param FilterGroup $filterGroup
     * @param AbstractDb $collection
     * @return void
     */
    protected function addFilterGroupToCollection(
        FilterGroup $filterGroup,
        AbstractDb $collection
    ) {
        foreach ($filterGroup->getFilters() as $filter) {
            if (in_array($filter->getField(), $this->ignoredFilters)) {
                continue;
            }

            $isApplied = false;
            $customFilter = $this->getCustomFilterForField($filter->getField());

            if ($customFilter) {
                $isApplied = $customFilter->apply($filter, $collection);
            }

            if (!$isApplied) {
                $filter->setField($this->getFieldMapping($filter->getField()));
                $this->defaultFilter->apply($filter, $collection);
            }
        }
    }
}
