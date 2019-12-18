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

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Data\Collection\AbstractDb;

class FilterProcessor implements CollectionProcessorInterface
{
    /**
     * @var CustomFilterInterface[]
     */
    private $customFilters;

    /**
     * @var array
     */
    private $fieldMapping;

    /**
     * @var CustomFilterInterface
     */
    private $defaultFilter;


    /**
     * @param CustomFilterInterface $defaultFilter
     * @param CustomFilterInterface[] $customFilters
     * @param array $fieldMapping
     */
    public function __construct(
        CustomFilterInterface $defaultFilter,
        array $customFilters = [],
        array $fieldMapping = []
    ) {
        $this->defaultFilter = $defaultFilter;
        $this->customFilters = $customFilters;
        $this->fieldMapping = $fieldMapping;
    }

    /**
     * Apply Search Criteria Filters to collection
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param AbstractDb $collection
     * @return void
     */
    public function process(SearchCriteriaInterface $searchCriteria, AbstractDb $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
    }

    /**
     * Add FilterGroup to the collection
     *
     * @param FilterGroup $filterGroup
     * @param AbstractDb $collection
     * @return void
     */
    private function addFilterGroupToCollection(
        FilterGroup $filterGroup,
        AbstractDb $collection
    ) {
        foreach ($filterGroup->getFilters() as $filter) {
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

    /**
     * Return custom filters for field if exists
     *
     * @param string $field
     * @return CustomFilterInterface|null
     * @throws \InvalidArgumentException
     */
    private function getCustomFilterForField($field)
    {
        $filter = null;
        if (isset($this->customFilters[$field])) {
            $filter = $this->customFilters[$field];
            if (!($this->customFilters[$field] instanceof CustomFilterInterface)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Filter for %s must implement %s interface.',
                        $field,
                        CustomFilterInterface::class
                    )
                );
            }
        }
        return $filter;
    }

    /**
     * Return mapped field name
     *
     * @param string $field
     * @return string
     */
    private function getFieldMapping($field)
    {
        return isset($this->fieldMapping[$field]) ? $this->fieldMapping[$field] : $field;
    }
}
