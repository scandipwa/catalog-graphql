<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Denis Protassoff <info@scandiweb.com>
 * @copyright   Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\DataProvider\Product;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder as MagentoSearchCriteriaBuilder;

/**
 * Class SearchCriteriaBuilder
 *
 * @package ScandiPWA\CatalogGraphQl\DataProvider\Product
 */
class SearchCriteriaBuilder extends MagentoSearchCriteriaBuilder
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @var Builder
     */
    protected $builder;
    /**
     * @var Visibility
     */
    protected $visibility;

    /**
     * @var SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @param Builder $builder
     * @param ScopeConfigInterface $scopeConfig
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param Visibility $visibility
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        Builder $builder,
        ScopeConfigInterface $scopeConfig,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        Visibility $visibility,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->builder = $builder;
        $this->visibility = $visibility;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * Build search criteria
     *
     * @param array $args
     * @param bool $includeAggregation
     * @return SearchCriteriaInterface
     */
    public function build(array $args, bool $includeAggregation): SearchCriteriaInterface
    {
        $searchCriteria = $this->builder->build('products', $args);
        $isSearch = !empty($args['search']);
        $this->updateRangeFilters($searchCriteria);

        if ($includeAggregation) {
            $this->preparePriceAggregation($searchCriteria);
            $requestName = 'graphql_product_search_with_aggregation';
        } else {
            $requestName = 'graphql_product_search';
        }

        $searchCriteria->setRequestName($requestName);

        if ($isSearch) {
            $this->addFilter($searchCriteria, 'search_term', $args['search']);
        }

        if (!$searchCriteria->getSortOrders()) {
            $this->addDefaultSortOrder($searchCriteria, $args, $isSearch);
        }

        $this->addEntityIdSort($searchCriteria, $args);
        // Removed $isFilter parameter
        $this->addVisibilityFilter($searchCriteria, $isSearch);

        $searchCriteria->setCurrentPage($args['currentPage']);
        $searchCriteria->setPageSize($args['pageSize']);

        return $searchCriteria;
    }

    /**
     * Changed to always add visibility filter
     * Add filter by visibility
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param bool $isSearch
     * @param bool $isFilter
     */
    protected function addVisibilityFilter(SearchCriteriaInterface $searchCriteria, bool $isSearch): void
    {
        // Removed $isFilter parameter and related check
        $visibilityIds = $isSearch
            ? $this->visibility->getVisibleInSearchIds()
            : $this->visibility->getVisibleInCatalogIds();

        $this->addFilter($searchCriteria, 'visibility', $visibilityIds, 'in');
    }

    /**
     * Add sort by Entity ID
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $args
     */
    protected function addEntityIdSort(SearchCriteriaInterface $searchCriteria, array $args): void
    {
        $sortOrder = !empty($args['sort']) ? reset($args['sort']) : SortOrder::SORT_DESC;
        $sortOrderArray = $searchCriteria->getSortOrders();
        $sortOrderArray[] = $this->sortOrderBuilder
            ->setField('_id')
            ->setDirection($sortOrder)
            ->create();
        $searchCriteria->setSortOrders($sortOrderArray);
    }

    /**
     * Prepare price aggregation algorithm
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     */
    protected function preparePriceAggregation(SearchCriteriaInterface $searchCriteria): void
    {
        $priceRangeCalculation = $this->scopeConfig->getValue(
            \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory::XML_PATH_RANGE_CALCULATION,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($priceRangeCalculation) {
            $this->addFilter($searchCriteria, 'price_dynamic_algorithm', $priceRangeCalculation);
        }
    }

    /**
     * Add filter to search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param string $field
     * @param mixed $value
     * @param string|null $condition
     */
    protected function addFilter(
        SearchCriteriaInterface $searchCriteria,
        string $field,
        $value,
        ?string $condition = null
    ): void {
        $filter = $this->filterBuilder
            ->setField($field)
            ->setValue($value)
            ->setConditionType($condition)
            ->create();

        $this->filterGroupBuilder->addFilter($filter);
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
    }

    /**
     * Sort by relevance DESC by default
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param array $args
     * @param bool $isSearch
     */
    protected function addDefaultSortOrder(SearchCriteriaInterface $searchCriteria, array $args, $isSearch = false): void
    {
        $defaultSortOrder = [];

        if ($isSearch) {
            $defaultSortOrder[] = $this->sortOrderBuilder
                ->setField('relevance')
                ->setDirection(SortOrder::SORT_DESC)
                ->create();
        } else {
            $categoryIdFilter = isset($args['filter']['category_id']) ? $args['filter']['category_id'] : false;

            if ($categoryIdFilter) {
                if (!is_array($categoryIdFilter[array_key_first($categoryIdFilter)])
                    || count($categoryIdFilter[array_key_first($categoryIdFilter)]) <= 1
                ) {
                    $defaultSortOrder[] = $this->sortOrderBuilder
                        ->setField(EavAttributeInterface::POSITION)
                        ->setDirection(SortOrder::SORT_ASC)
                        ->create();
                }
            }
        }

        $searchCriteria->setSortOrders($defaultSortOrder);
    }

    /**
     * Format range filters so replacement works
     *
     * Range filter fields in search request must replace value like '%field.from%' or '%field.to%'
     *
     * @param SearchCriteriaInterface $searchCriteria
     */
    protected function updateRangeFilters(SearchCriteriaInterface $searchCriteria): void
    {
        $filterGroups = $searchCriteria->getFilterGroups();

        foreach ($filterGroups as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                if (in_array($filter->getConditionType(), ['from', 'to'])) {
                    $filter->setField($filter->getField() . '.' . $filter->getConditionType());
                }
            }
        }
    }
}
