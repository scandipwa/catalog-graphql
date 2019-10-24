<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogGraphQl\Model\Resolver\Products\Attributes\Collection as AttributeCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\ConfigurableProductGraphQl\Model\Options\Collection as OptionCollection;
use Magento\ConfigurableProductGraphQl\Model\Variant\Collection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Helper\Attributes;
use ScandiPWA\CatalogGraphQl\Helper\Images;
use ScandiPWA\CatalogGraphQl\Helper\Stocks;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\AttributeProcessor;

/**
 * @inheritdoc
 */
class ConfigurableVariant implements ResolverInterface
{
    const MEDIA_GALLERY_ENTRIES = 'media_gallery_entries';

    /**
     * @var Collection
     */
    private $variantCollection;

    /**
     * @var OptionCollection
     */
    private $optionCollection;

    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var AttributeCollection
     */
    private $attributeCollection;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var Attributes
     */
    protected $attributes;

    /**
     * @var Images
     */
    protected $images;

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
     * @var Stocks
     */
    protected $stocks;

    /**
     * @param Collection $variantCollection
     * @param OptionCollection $optionCollection
     * @param ValueFactory $valueFactory
     * @param AttributeCollection $attributeCollection
     * @param MetadataPool $metadataPool
     * @param Attributes $attributes
     * @param Images $images
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Stocks $stocks
     */
    public function __construct(
        Collection $variantCollection,
        OptionCollection $optionCollection,
        ValueFactory $valueFactory,
        AttributeCollection $attributeCollection,
        MetadataPool $metadataPool,
        Attributes $attributes,
        Images $images,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        Stocks $stocks
    ) {
        $this->variantCollection = $variantCollection;
        $this->optionCollection = $optionCollection;
        $this->valueFactory = $valueFactory;
        $this->attributeCollection = $attributeCollection;
        $this->metadataPool = $metadataPool;
        $this->attributes = $attributes;
        $this->images = $images;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->stocks = $stocks;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        if ($value['type_id'] !== Type::TYPE_CODE || !isset($value[$linkField])) {
            $result = function () {
                return null;
            };

            return $this->valueFactory->create($result);
        }

        $this->variantCollection->addParentProduct($value['model']);
        $fields = $this->getProductFields($info);
        $this->processAttributes($fields);

        $result = function () use ($value, $linkField, $info) {
            $children = $this->variantCollection->getChildProductsByParentId((int)$value[$linkField]);
            $variants = [];

            $products = array_map(function ($product) {
                return $product['model'];
            }, $children);

            $attributes = $this->attributes->getProductAttributes($products, $info);
            $images = $this->images->getProductImages($products, $info);
            $stocks = $this->stocks->getProductStocks($products, $info);

            /** @var Product $child */
            foreach ($children as $key => $child) {
                $product = $child['model'];
                $id = $product->getId();

                if (isset($images[$id])) {
                    foreach ($images[$id] as $imageType => $imageData) {
                        $child[$imageType] = $imageData;
                    }
                }

                if (isset($attributes[$id])) {
                    $child['attributes'] = $attributes[$id];
                }

                if (isset($stocks[$id])) {
                    foreach ($stocks[$id] as $stockType => $stockData) {
                        $child[$stockType] = $stockData;
                    }
                }

                $variants[$key] = [
                    'sku' => $child['sku'],
                    'product' => $child,
                ];
            }

            return $variants;
        };

        return $this->valueFactory->create($result);
    }

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo $info
     * @return string[]
     */
    private function getProductFields(ResolveInfo $info)
    {
        $fieldNames = [];

        foreach ($info->fieldNodes as $node) {
            if (!isset($node->name)) continue;

            if ($node->name->value !== 'variants') {
                continue;
            }

            foreach ($node->selectionSet->selections as $selectionNode) {
                if (!isset($selectionNode->name)) continue;

                if ($selectionNode->name->value !== 'product') {
                    continue;
                }

                foreach ($selectionNode->selectionSet->selections as $productSelection) {
                    if (!isset($selectionNode->name)) continue;

                    $fieldNames[] = $productSelection->name->value;
                }
            }
        }

        return $fieldNames;
    }

    protected function processAttributes($attributeNames) {
        $attributesToRequest = [];

        foreach ($attributeNames as $name) {
            switch ($name) {
                case AttributeProcessor::ATTRIBUTES_FIELD:
                    if (!isset($this->attributesVisibleOnFrontend)) {
                        $this->getAttributesVisibleOnFrontend();
                    }

                    $attributeCodes = array_map(function($attr) {
                        return $attr->getAttributeCode();
                    }, $this->attributesVisibleOnFrontend);

                    $attributesToRequest = array_merge($attributeCodes, $attributesToRequest);
                    break;
                case self::MEDIA_GALLERY_ENTRIES:
                default:
                    $attributesToRequest[] = $name;
                    break;
            }
        }

        $this->variantCollection->addEavAttributes($attributesToRequest);
    }

    protected function getAttributesVisibleOnFrontend() {
        $collection = $this->collectionFactory->create();
        $collection->setItemObjectClass(Attribute::class)
            ->addFieldToSelect('attribute_code')
            ->addFieldToSelect('attribute_id')
            ->addStoreLabel($this->storeManager->getStore()->getId())
            ->setOrder('position', 'ASC');

        // Add filter by storefront visibility
        $collection->addFieldToFilter('additional_table.is_visible_on_front', ['gt' => 0]);

        // Cache in this ???
        $this->attributesVisibleOnFrontend = $collection->getItems();
    }
}
