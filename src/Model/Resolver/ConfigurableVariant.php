<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\Attributes\Collection as AttributeCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\ConfigurableProductGraphQl\Model\Options\Collection as OptionCollection;
use Magento\ConfigurableProductGraphQl\Model\Variant\Collection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

/**
 * Class ConfigurableVariant
 *
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class ConfigurableVariant
{
    use ResolveInfoFieldsTrait;

    /**
     * @var Collection
     */
    protected $variantCollection;

    /**
     * @var OptionCollection
     */
    protected $optionCollection;

    /**
     * @var ValueFactory
     */
    protected $valueFactory;

    /**
     * @var AttributeCollection
     */
    protected $attributeCollection;

    /**
     * @var MetadataPool
     */
    protected $metadataPool;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DataObject[]
     */
    protected $attributesVisibleOnFrontend;

    /**
     * @var DataPostProcessor
     */
    protected $productPostProcessor;

    /**
     * ConfigurableVariant constructor.
     * @param Collection $variantCollection
     * @param OptionCollection $optionCollection
     * @param ValueFactory $valueFactory
     * @param AttributeCollection $attributeCollection
     * @param MetadataPool $metadataPool
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param DataPostProcessor $productPostProcessor
     */
    public function __construct(
        Collection $variantCollection,
        OptionCollection $optionCollection,
        ValueFactory $valueFactory,
        AttributeCollection $attributeCollection,
        MetadataPool $metadataPool,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        DataPostProcessor $productPostProcessor
    ) {
        $this->variantCollection = $variantCollection;
        $this->optionCollection = $optionCollection;
        $this->valueFactory = $valueFactory;
        $this->attributeCollection = $attributeCollection;
        $this->metadataPool = $metadataPool;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->productPostProcessor = $productPostProcessor;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        if ($value['type_id'] !== Type::TYPE_CODE || !isset($value[$linkField])) {
            $result = function () {
                return null;
            };

            return $this->valueFactory->create($result);
        }

        // Configure variant collection
        $this->variantCollection->addParentProduct($value['model']);
        $fields = $this->getFieldsFromProductInfo($info, 'variants/product');
        $this->variantCollection->addEavAttributes($fields);

        $result = function () use ($value, $linkField, $info, $args) {
            $children = $this->variantCollection->getChildProductsByParentId((int) $value[$linkField]);

            $products = array_map(function ($product) {
                return $product['model'];
            }, $children);

            $products = array_map(function ($product) {
                return [
                    'product' => $product,
                    'sku' => $product['sku']
                ];
            }, $this->productPostProcessor->process(
                $products,
                'variants/product',
                $info
            ));

            return $products;
        };

        return $this->valueFactory->create($result);
    }
}
