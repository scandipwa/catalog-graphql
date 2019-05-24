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

use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Swatches\Helper\Data;

use Magento\Catalog\Model\ProductRepository;

class AttributesWithValue implements ResolverInterface
{
    /**
     * @var Data
     */
    private $swatchHelper;

    private $productRepository;

    /**
     * CustomAttributes constructor.
     * @param Data $swatchHelper
     */
    public function __construct(
        Data $swatchHelper,
        ProductRepository $productRepository
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productRepository = $productRepository;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
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
        $product = $this->productRepository->getById($value['entity_id']);
//        $product = isset($value['product']) ? $value['product']['model'] : $value['model'];
        $attributes = $product->getAttributes();
        $attributesToSelect = array_unique($args['attributes']);
        $attributesToReturn = [];

        foreach ($attributesToSelect as $attributeToSelect) {
            $attribute = $product->getCustomAttribute($attributeToSelect);

            if (isset($attributes[$attributeToSelect])) {
                $fullAttributeData = $attributes[$attributeToSelect];
                $rawOptions = $fullAttributeData->getSource()->getAllOptions(true, true);
                array_shift($rawOptions);

                $optionIds = array_map(function ($option) { return $option['value']; }, $rawOptions);
                $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

                $attributesToReturn[] = [
                    'attribute_value' => $attribute ? $attribute->getValue() : null,
                    'attribute_code' => $fullAttributeData->getAttributeCode(),
                    'attribute_type' => $fullAttributeData->getFrontendInput(),
                    'attribute_label' => $fullAttributeData->getFrontendLabel(),
                    'attribute_id' => $fullAttributeData->getAttributeId(),
                    'attribute_options' => array_map(function ($option) use ($swatchOptions) {
                        $option['swatch_data'] = $swatchOptions[$option['value']] ?? [];
                        return $option;
                    }, $rawOptions)
                ];
            }
        }

        return $attributesToReturn;
    }
}