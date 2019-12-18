<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data;
use Magento\Catalog\Model\ProductRepository;

class AttributesWithValue implements ResolverInterface
{
    /**
     * @var Data
     */
    protected $swatchHelper;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * CustomAttributes constructor.
     * @param Data $swatchHelper
     * @param ProductRepository $productRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $swatchHelper,
        ProductRepository $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    protected function getAttributeOptions($attr, $rawOptions) {
        $optionIds = array_map(function ($option) {
            return $option['value'];
        }, $rawOptions);

        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

        if (!$this->swatchHelper->isSwatchAttribute($attr)) {
            return $rawOptions;
        }

        return array_map(function ($option) use ($swatchOptions) {
            $option['swatch_data'] = $swatchOptions[$option['value']] ?? [];
            return $option;
        }, $rawOptions);
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $product = $value['model'];
        $attributesToReturn = [];

        $storeId = $this->storeManager->getStore()->getId();
        /** @var $attr Attribute */

        foreach ($product->getAttributes() as $attr) {
            if ($attr->getIsVisibleOnFront()) {
                $productAttr = $product->getCustomAttribute($attr->getAttributeCode());

                $rawOptions = $attr->getSource()->getAllOptions(true, false);
                array_shift($rawOptions);

                $attributesToReturn[] = [
                    'attribute_value' => $productAttr ? $productAttr->getValue() : null,
                    'attribute_code' => $attr->getAttributeCode(),
                    'attribute_type' => $attr->getFrontendInput(),
                    'attribute_label' => $attr->getStoreLabel(),
                    'attribute_id' => $attr->getAttributeId(),
                    'attribute_options' => $this->getAttributeOptions($attr, $rawOptions)
                ];
            }
        }

        return $attributesToReturn;
    }
}
