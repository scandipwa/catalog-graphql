<?php
/**
 * @category  ScandiPWA
 * @package   ScandiPWA_CatalogGraphQl
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Filter;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch\ProductCollectionSearchCriteriaBuilder;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use Magento\Catalog\Model\Product\Visibility;

/**
 * Class AddVisibilityFilterOnSingleProductRequests
 * @package ScandiPWA\CatalogGraphQl\Model\Plugin
 */
class AddVisibilityFilterOnSingleProductRequests
{
    /**
     * @var FilterBuilder
     */
    protected FilterBuilder $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected FilterGroupBuilder $filterGroupBuilder;

    /**
     * @var Visibility
     */
    protected Visibility $visibility;

    /**
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param Visibility $visibility
     */
    public function __construct(
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        Visibility $visibility
    ) {
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->visibility = $visibility;
    }

    /**
     * Applies visibility filter to single product collection loads
     * Necessary, because ES querywas skipped for performance
     * @param ProductCollectionSearchCriteriaBuilder $subject
     * @param SearchCriteriaInterface $searchCriteriaForCollection
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    public function afterBuild(
        ProductCollectionSearchCriteriaBuilder $subject,
        SearchCriteriaInterface $searchCriteriaForCollection,
        SearchCriteriaInterface $searchCriteria
    ): SearchCriteriaInterface {
        if (CriteriaCheck::isOnlySingleIdFilter($searchCriteria) &&
            $visibilityFilter = CriteriaCheck::getVisibilityFilter($searchCriteria)) {
            // Single product collection should have all 'visible in site' filters
            $visibilityFilter->setValue($this->visibility->getVisibleInSiteIds());
            $filterForCollection = $this->createVisibilityFilter($visibilityFilter);
            $this->filterGroupBuilder->addFilter($filterForCollection);
            $visibilityGroup = $this->filterGroupBuilder->create();
            $filterGroups = $searchCriteriaForCollection->getFilterGroups();
            $filterGroups[] = $visibilityGroup;
            $searchCriteriaForCollection->setFilterGroups($filterGroups);
        }

        return $searchCriteriaForCollection;
    }

    /**
     * Creates a collection filter based off of ES filter
     * @param Filter $filter
     * @return Filter
     */
    public function createVisibilityFilter(Filter $filter): Filter
    {
        return $this->filterBuilder
            ->setField($filter->getField())
            ->setValue($filter->getValue())
            ->setConditionType($filter->getConditionType())
            ->create();
    }
}
