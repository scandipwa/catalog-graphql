<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Variant;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection as ChildCollection;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\CatalogInventory\Helper\Stock;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as DataProvider;
use Magento\ConfigurableProductGraphQl\Model\Variant\Collection as MagentoCollection;
use Magento\Review\Model\Review;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;

/**
 * Collection for fetching configurable child product data.
 */
class Collection extends MagentoCollection
{
    /**
     * @var CollectionFactory
     */
    private $childCollectionFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var DataProvider
     */
    private $productDataProvider;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var Stock
     */
    private $stock;

    /**
     * @var Product[]
     */
    private $parentProducts = [];

    /**
     * @var array
     */
    private $childrenMap = [];

    /**
     * @var string[]
     */
    private $attributeCodes = [];

    /**
     * @var Review
     */
    protected $review;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var CollectionPostProcessor
     */
    protected $postProcessor;

    /**
     * @param CollectionFactory $childCollectionFactory
     * @param ProductFactory $productFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataProvider $productDataProvider
     * @param MetadataPool $metadataPool
     * @param Review $review
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Stock $stock
     * @param CollectionPostProcessor $postProcessor
     */
    public function __construct(
        CollectionFactory $childCollectionFactory,
        ProductFactory $productFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataProvider $productDataProvider,
        MetadataPool $metadataPool,
        Review $review,
        CollectionProcessorInterface $collectionProcessor,
        Stock $stock,
        CollectionPostProcessor $postProcessor
    ) {
        parent::__construct(
            $childCollectionFactory,
            $searchCriteriaBuilder,
            $metadataPool,
            $collectionProcessor
        );

        $this->childCollectionFactory = $childCollectionFactory;
        $this->productFactory = $productFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productDataProvider = $productDataProvider;
        $this->metadataPool = $metadataPool;
        $this->review = $review;
        $this->collectionProcessor = $collectionProcessor;
        $this->stock = $stock;
        $this->postProcessor = $postProcessor;
    }

    /**
     * Add parent to collection filter
     *
     * @param Product $product
     * @return void
     * @throws Exception
     */
    public function addParentProduct(Product $product) : void
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $productId = $product->getData($linkField);

        if (isset($this->parentProducts[$productId])) {
            return;
        }

        if (!empty($this->childrenMap)) {
            $this->childrenMap = [];
        }
        $this->parentProducts[$productId] = $product;
    }

    /**
     * Add attributes to collection filter
     *
     * @param array $attributeCodes
     * @return void
     */
    public function addEavAttributes(array $attributeCodes) : void
    {
        $this->attributeCodes = array_replace($this->attributeCodes, $attributeCodes);
    }

    /**
     * Retrieve child products from for passed in parent id.
     *
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function getChildProductsByParentId(int $id) : array
    {
        $childrenMap = $this->fetch();

        if (!isset($childrenMap[$id])) {
            return [];
        }

        return $childrenMap[$id];
    }

    /**
     * Fetch all children products from parent id's.
     *
     * @return array
     * @throws Exception
     */
    private function fetch() : array
    {
        if (empty($this->parentProducts) || !empty($this->childrenMap)) {
            return $this->childrenMap;
        }

        foreach ($this->parentProducts as $product) {
            $attributes = $this->attributeCodes;

            /** @var ChildCollection $childCollection */
            $childCollection = $this->childCollectionFactory->create();
            $childCollection->setProductFilter($product);

            $this->collectionProcessor->process(
                $childCollection,
                $this->searchCriteriaBuilder->create(),
                $attributes
            );

            $this->postProcessor->process(
                $childCollection,
                $attributes
            );

            /** @var Product $childProduct */
            foreach ($childCollection->getItems() as $childProduct) {
                $formattedChild = [
                    'model' => $childProduct,
                    'sku' => $childProduct->getSku()
                ];

                $parentId = (int) $childProduct->getParentId();

                if (!isset($this->childrenMap[$parentId])) {
                    $this->childrenMap[$parentId] = [];
                }

                $this->childrenMap[$parentId][] = $formattedChild;
            }
        }

        return $this->childrenMap;
    }
}
