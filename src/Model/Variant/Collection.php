<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Variant;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection as ChildCollection;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\ConfigurableProductGraphQl\Model\Variant\Collection as MagentoCollection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionPostProcessor;
use function in_array;

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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

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
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var CollectionPostProcessor
     */
    private $collectionPostProcessor;

    /**
     * @var SearchCriteriaInterface
     */
    protected $searchCriteria;

    /**
     * Collection constructor.
     *
     * @param CollectionFactory $childCollectionFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param MetadataPool $metadataPool
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     */
    public function __construct(
        CollectionFactory $childCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        MetadataPool $metadataPool,
        CollectionProcessorInterface $collectionProcessor,
        CollectionPostProcessor $collectionPostProcessor
    ) {
        parent::__construct(
            $childCollectionFactory,
            $searchCriteriaBuilder,
            $metadataPool,
            $collectionProcessor,
            $collectionPostProcessor
        );

        $this->childCollectionFactory = $childCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->metadataPool = $metadataPool;
        $this->collectionProcessor = $collectionProcessor;
        $this->collectionPostProcessor = $collectionPostProcessor;

        $this->searchCriteria = $this->searchCriteriaBuilder->create();
    }

    /**
     * Add parent to collection filter
     *
     * @param Product $product
     * @return void
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
     * @param SearchCriteriaInterface $searchCriteria
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;
    }

    /**
     * Fetch all children products from parent id's.
     *
     * @return array
     */
    private function fetch() : array
    {
        if (empty($this->parentProducts) || !empty($this->childrenMap)) {
            return $this->childrenMap;
        }

        foreach ($this->parentProducts as $product) {
            $attributeData = $this->getAttributesCodes($product);
            /** @var ChildCollection $childCollection */
            $childCollection = $this->childCollectionFactory->create();
            $childCollection->setProductFilter($product);
            $this->collectionProcessor->process(
                $childCollection,
                $this->searchCriteria,
                $attributeData
            );
            $childCollection->load();
            $this->collectionPostProcessor->process($childCollection, $attributeData);

            /** @var Product $childProduct */
            foreach ($childCollection as $childProduct) {
                $formattedChild = ['model' => $childProduct, 'sku' => $childProduct->getSku()];
                $parentId = (int)$childProduct->getParentId();
                if (!isset($this->childrenMap[$parentId])) {
                    $this->childrenMap[$parentId] = [];
                }

                $this->childrenMap[$parentId][] = $formattedChild;
            }
        }

        return $this->childrenMap;
    }

    /**
     * Get attributes code
     *
     * @param Product $currentProduct
     * @return array
     */
    private function getAttributesCodes(Product $currentProduct): array
    {
        $attributeCodes = $this->attributeCodes;
        $allowAttributes = $currentProduct->getTypeInstance()->getConfigurableAttributes($currentProduct);
        foreach ($allowAttributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            if (!in_array($productAttribute->getAttributeCode(), $attributeCodes)) {
                $attributeCodes[] = $productAttribute->getAttributeCode();
            }
        }

        return $attributeCodes;
    }
}
