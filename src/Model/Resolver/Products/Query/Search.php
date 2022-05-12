<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Exception;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Search\Model\Search\PageSizeProvider;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\FieldSelection;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Search as CoreSearch;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\EmulateSearchResult;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

/**
 * Full text search for catalog using given search criteria.
 */
class Search extends CoreSearch
{
    /**
     * @var SearchInterface
     */
    private $search;

    /**
     * @var SearchResultFactory
     */
    private $searchResultFactory;

    /**
     * @var PageSizeProvider
     */
    private $pageSizeProvider;

    /**
     * @var FieldSelection
     */
    private $fieldSelection;

    /**
     * @var ProductSearch
     */
    private $productsProvider;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var DataPostProcessor
     */
    protected $productPostProcessor;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductSearchResultsInterfaceFactory
     */
    protected $productSearchResultsInterfaceFactory;

    /**
     * @var EmulateSearchResult
     */
    protected $emulateSearchResult;

    /**
     * @var CategoryCollectionFactory
     */
    protected CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param SearchInterface $search
     * @param SearchResultFactory $searchResultFactory
     * @param ProductSearchResultsInterfaceFactory $productSearchResultsInterfaceFactory
     * @param EmulateSearchResult $emulateSearchResult
     * @param PageSizeProvider $pageSize
     * @param FieldSelection $fieldSelection
     * @param ProductSearch $productsProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $productPostProcessor
     * @param QueryFactory $queryFactory
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        SearchInterface $search,
        SearchResultFactory $searchResultFactory,
        ProductSearchResultsInterfaceFactory $productSearchResultsInterfaceFactory,
        EmulateSearchResult $emulateSearchResult,
        PageSizeProvider $pageSize,
        FieldSelection $fieldSelection,
        ProductSearch $productsProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $productPostProcessor,
        QueryFactory $queryFactory,
        StoreManagerInterface $storeManager,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct(
            $search,
            $searchResultFactory,
            $pageSize,
            $fieldSelection,
            $productsProvider,
            $searchCriteriaBuilder
        );

        $this->search = $search;
        $this->searchResultFactory = $searchResultFactory;
        $this->pageSizeProvider = $pageSize;
        $this->fieldSelection = $fieldSelection;
        $this->productsProvider = $productsProvider;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productPostProcessor = $productPostProcessor;
        $this->queryFactory = $queryFactory;
        $this->storeManager = $storeManager;
        $this->productSearchResultsInterfaceFactory = $productSearchResultsInterfaceFactory;
        $this->emulateSearchResult = $emulateSearchResult;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Return product search results using Search API
     *
     * @param array $args
     * @param ResolveInfo $info
     * @param ContextInterface $context
     * @return SearchResult
     * @throws Exception
     */
    public function getResult(
        array $args,
        ResolveInfo $info,
        ContextInterface $context
    ): SearchResult {
        $queryFields = $this->fieldSelection->getProductsFieldSelection($info);
        $searchCriteria = $this->buildSearchCriteria($args, $info);
        $itemsResults = $this->getSearchResults($searchCriteria, $info);

        // When adding a new product through the admin panel, it does not appear
        // on the category page (without cleaning cache), if the category won`t
        // mentions in request in Magento Tags (and not just product Tags as now).
        //
        // To add the category to tags, need to cause category loading when receiving products for it
        // (only loading is enough because the tags are added to load_after)
        //
        // Related task: https://github.com/scandipwa/scandipwa/issues/4353
        if (!empty($args['filter']['category_id'])) {
            $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('entity_id', $args['filter']['category_id'])
                ->load();
        }

        if ($this->includeItems($info)) {
            // load product collection only if items are requested
            $searchResults = $this->productsProvider->getList(
                $searchCriteria,
                $itemsResults,
                $queryFields,
                $context
            );
        } else {
            $searchResults = $this->productSearchResultsInterfaceFactory->create();
            $searchResults->setSearchCriteria($searchCriteria);
            $searchResults->setTotalCount($itemsResults->getTotalCount());
        }

        $totalPages = $searchCriteria->getPageSize() ?
            ((int)ceil($searchResults->getTotalCount() / $searchCriteria->getPageSize())) : 0;

        // Following lines are added to increment search terms
        if (!empty($args['search']) && strlen(trim($args['search']))) {
            $this->incrementQuery($args['search'], $searchResults->getTotalCount());
        }

        // Following lines are changed
        if (count($queryFields) > 0) {
            $productArray = $this->productPostProcessor->process(
                $searchResults->getItems(),
                'products/items',
                $info,
                ['isSingleProduct' => CriteriaCheck::isSingleProductFilter($searchCriteria)]
            );
        } else {
            $productArray = array_map(function ($product) {
                return $product->getData() + ['model' => $product];
            }, $searchResults->getItems());
        }

        return $this->searchResultFactory->create(
            [
                'totalCount' => $searchResults->getTotalCount(),
                'productsSearchResult' => $productArray,
                'searchAggregation' => $itemsResults->getAggregations(),
                'pageSize' => $searchCriteria->getPageSize(),
                'currentPage' => $searchCriteria->getCurrentPage(),
                'totalPages' => $totalPages,
            ]
        );
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param ResolveInfo $info
     * @return SearchResultInterface
     */
    private function getSearchResults(SearchCriteriaInterface $searchCriteria, ResolveInfo $info): SearchResultInterface
    {
        if (CriteriaCheck::isOnlySingleIdFilter($searchCriteria)) {
            return $this->emulateSearchResult->execute($searchCriteria);
        }

        $realPageSize =  $searchCriteria->getPageSize();
        $realCurrentPage = $searchCriteria->getCurrentPage();

        // Because of limitations of sort and pagination on search API we will query all IDS
        $pageSize = $this->pageSizeProvider->getMaxPageSize();
        $searchCriteria->setPageSize($pageSize);
        $searchCriteria->setCurrentPage(0);

        $itemsResults = $this->search->search($searchCriteria);

        $searchCriteria->setPageSize($realPageSize);
        $searchCriteria->setCurrentPage($realCurrentPage);

        return $itemsResults;
    }

    /**
     * Build search criteria from query input args
     *
     * @param array $args
     * @param ResolveInfo $info
     * @return SearchCriteriaInterface
     */
    private function buildSearchCriteria(array $args, ResolveInfo $info): SearchCriteriaInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder->build($args, $this->includeAggregations($info));

        return $searchCriteria;
    }

    /**
     * @param ResolveInfo $info
     * @return bool
     */
    private function includeAggregations(ResolveInfo $info): bool
    {
        $productFields = (array)$info->getFieldSelection(1);

        return isset($productFields['filters']) || isset($productFields['aggregations']);
    }

    /**
     * @param ResolveInfo $info
     * @return bool
     */
    private function includeItems(ResolveInfo $info): bool
    {
        $productFields = (array)$info->getFieldSelection(1);

        return isset($productFields['items']);
    }

    /**
     * @param $queryText
     * @param $queryResultCount
     * @return void
     */
    private function incrementQuery($queryText, $queryResultCount) {
        $query = $this->queryFactory->get();
        $query->setQueryText($queryText);
        $query->setNumResults($queryResultCount);
        $query->setStoreId($this->storeManager->getStore()->getId());
        $query->saveIncrementalPopularity();
        $query->saveNumResults($queryResultCount);
    }
}
