<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Exception;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
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
     * @param SearchInterface $search
     * @param SearchResultFactory $searchResultFactory
     * @param PageSizeProvider $pageSize
     * @param FieldSelection $fieldSelection
     * @param ProductSearch $productsProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $productPostProcessor
     * @param QueryFactory $queryFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        SearchInterface $search,
        SearchResultFactory $searchResultFactory,
        PageSizeProvider $pageSize,
        FieldSelection $fieldSelection,
        ProductSearch $productsProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $productPostProcessor,
        QueryFactory $queryFactory,
        StoreManagerInterface $storeManager
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

        $realPageSize = $searchCriteria->getPageSize();
        $realCurrentPage = $searchCriteria->getCurrentPage();
        //Because of limitations of sort and pagination on search API we will query all IDS
        $pageSize = $this->pageSizeProvider->getMaxPageSize();
        $searchCriteria->setPageSize($pageSize);
        $searchCriteria->setCurrentPage(0);
        $itemsResults = $this->search->search($searchCriteria);

        //Address limitations of sort and pagination on search API apply original pagination from GQL query
        $searchCriteria->setPageSize($realPageSize);
        $searchCriteria->setCurrentPage($realCurrentPage);
        $searchResults = $this->productsProvider->getList(
            $searchCriteria,
            $itemsResults,
            $queryFields,
            $context
        );

        $totalPages = $realPageSize ? ((int)ceil($searchResults->getTotalCount() / $realPageSize)) : 0;

        $searchCriteria->setPageSize($realPageSize);
        $searchCriteria->setCurrentPage($realCurrentPage);

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
                'pageSize' => $realPageSize,
                'currentPage' => $realCurrentPage,
                'totalPages' => $totalPages,
            ]
        );
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
        $productFields = (array)$info->getFieldSelection(1);
        $includeAggregations = isset($productFields['filters']) || isset($productFields['aggregations']);
        $searchCriteria = $this->searchCriteriaBuilder->build($args, $includeAggregations);

        return $searchCriteria;
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
