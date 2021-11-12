<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch\ProductCollectionSearchCriteriaBuilder as CoreProductCollectionSearchCriteriaBuilder;

/**
 * Class ProductCollectionSearchCriteriaBuilder
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch
 */
class ProductCollectionSearchCriteriaBuilder extends  CoreProductCollectionSearchCriteriaBuilder
{
    /**
     * @var SearchCriteriaInterfaceFactory
     */
    protected $searchCriteriaFactory;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @param SearchCriteriaInterfaceFactory $searchCriteriaFactory
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        SearchCriteriaInterfaceFactory $searchCriteriaFactory,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        $this->searchCriteriaFactory = $searchCriteriaFactory;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    /**
     * Build searchCriteria from search for product collection
     * Rewrite: removed addition of category filter, as ES search query already applies that filter
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    public function build(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        //Create a copy of search criteria without filters to preserve the results from search
        $searchCriteriaForCollection = $this->searchCriteriaFactory->create()
            ->setSortOrders($searchCriteria->getSortOrders())
            ->setPageSize($searchCriteria->getPageSize())
            ->setCurrentPage($searchCriteria->getCurrentPage());

        return $searchCriteriaForCollection;
    }
}
