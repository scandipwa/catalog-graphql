<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Raivis Dejus <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as MagentoProduct;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;

/**
 * Product field data provider, used for GraphQL resolver processing.
 * Adds support for price min and max values
 */
class Product extends MagentoProduct
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ProductSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var Visibility
     */
    protected $visibility;

    /**
     * @var float
     */
    protected $minPrice;

    /**
     * @var float
     */
    protected $maxPrice;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionPostProcessor
     */
    protected $postProcessor;

    /**
     * Product constructor.
     * @param CollectionFactory $collectionFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param Visibility $visibility
     * @param CollectionProcessorInterface $collectionProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionPostProcessor $postProcessor
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ProductSearchResultsInterfaceFactory $searchResultsFactory,
        Visibility $visibility,
        CollectionProcessorInterface $collectionProcessor,
        StoreManagerInterface $storeManager,
        CollectionPostProcessor $postProcessor
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->visibility = $visibility;
        $this->collectionProcessor = $collectionProcessor;
        $this->storeManager = $storeManager;
        $this->postProcessor = $postProcessor;
    }

    /**
     * Gets list of product data with full data set. Adds eav attributes to result set from passed in array
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param string[] $attributes
     * @param bool $isSearch
     * @param bool $isChildSearch
     * @return SearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
        array $attributes = [],
        bool $isSearch = false,
        bool $isChildSearch = false
    ): SearchResultsInterface {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($collection, $searchCriteria, $attributes);

        if (!$isChildSearch) {
            $singleProduct = CriteriaCheck::isSingleProductFilter($searchCriteria);
            if ($singleProduct) {
                $visibilityIds = $this->visibility->getVisibleInSiteIds();
            } else {
                $visibilityIds = $isSearch
                    ? $this->visibility->getVisibleInSearchIds()
                    : $this->visibility->getVisibleInCatalogIds();
            }
            $collection->setVisibility($visibilityIds);
        }

        $collection->load();

        $this->postProcessor->process($collection, $attributes);

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        list($this->minPrice,$this->maxPrice) = $this->getCollectionMinMaxPrice($collection);
        return $searchResult;
    }


    /**
     * @param Collection $collection
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCollectionMinMaxPrice($collection)
    {
        $connection = $collection->getConnection();
        $entityIds = $collection->getAllIds();
        $currencyRate = $collection->getCurrencyRate();

        $query = sprintf(
            'SELECT MIN(min_price) as min_price, MAX(max_price) as max_price FROM catalog_product_index_price WHERE entity_id IN ("%s") AND website_id = %d',
            implode('","', $entityIds),
            $this->storeManager->getStore()->getWebsiteId()
        );

        $row = $connection->fetchRow($query);

        return [
            floatval($row['min_price']) * $currencyRate,
            floatval($row['max_price']) * $currencyRate
        ];
    }

    /**
     * @return float
     */
    public function getMinPrice()
    {
        return $this->minPrice;
    }

    /**
     * @return float
     */
    public function getMaxPrice()
    {
        return $this->maxPrice;
    }
}
