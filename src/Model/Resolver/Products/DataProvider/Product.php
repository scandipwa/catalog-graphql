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
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CriteriaCheck;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review\Product\Collection as ProductCollection;

/**
 * Product field data provider, used for GraphQL resolver processing.
 * Adds support for price min and max values
 */
class Product extends \Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ProductSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var Visibility
     */
    private $visibility;

    /**
     * @var float
     */
    private $minPrice;

    /**
     * @var float
     */
    private $maxPrice;

    /**
     * @var Review
     */
    protected $review;

    /**
     * Product constructor.
     * @param CollectionFactory $collectionFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param Visibility $visibility
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Review $review
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ProductSearchResultsInterfaceFactory $searchResultsFactory,
        Visibility $visibility,
        CollectionProcessorInterface $collectionProcessor,
        Review $review
    ) {
        $this->review = $review;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->visibility = $visibility;
        $this->collectionProcessor = $collectionProcessor;
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

        // Methods that perform extra fetches post-load
        if (in_array('media_gallery_entries', $attributes)) {
            $collection->addMediaGalleryData();
        }
        if (in_array('options', $attributes)) {
            $collection->addOptionsToResult();
        }

        if (in_array('review_summary', $attributes)) {
            /** @var ProductCollection $collection */
            // Only getItems is used inside
            $this->review->appendSummary($collection);
        }

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
     */
    public function getCollectionMinMaxPrice($collection)
    {
        $connection = $collection->getConnection();
        $entityIds = $collection->getAllIds();

        $row = $connection->fetchRow('SELECT MIN(min_price) as min_price, MAX(max_price) as max_price FROM catalog_product_index_price WHERE entity_id IN(\'' . \implode("','", $entityIds) . '\')');

        return [floatval($row['min_price']),floatval($row['max_price'])];
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
