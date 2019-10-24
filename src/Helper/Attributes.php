<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Helper;

use GraphQL\Language\AST\FieldNode;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Swatches\Helper\Data;
use ScandiPWA\CatalogGraphQl\Helper\AbstractHelper;

class Attributes extends AbstractHelper {
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var Data
     */
    protected $swatchHelper;

    public function __construct(
        Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository,
        Data $swatchHelper
    ) {
        parent::__construct($context);

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->swatchHelper = $swatchHelper;
    }

    /**
     * @param $node
     * @return string[]
     */
    protected function getFieldContent($node) {
        $attributes = null;

        foreach($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) continue;
            if ($selection->name->value === 'attributes') {
                $attributes = $selection->selectionSet->selections;
                break;
            }
        }

        $fieldNames = [];

        if (is_iterable($attributes)) {
            foreach ($attributes as $attribute) {
                $fieldNames[] = $attribute->name->value;
            }
        }

        return $fieldNames;
    }

    /**
     * @param $products ExtensibleDataInterface[]
     * @param $info ResolveInfo
     * @return array
     */
    public function getProductAttributes($products, $info) {
        $productAttributes = [];
        $attributes = [];
        $swatchAttributes = [];

        $fields = $this->getFieldsFromProductInfo($info);
        $isCollectOptions = in_array('attribute_options', $fields);

        if (!count($fields)) {
            return [];
        }

        // Collect attributes for request
        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            // Create storage for future attributes
            $productAttributes[$id] = [];

            foreach ($product->getAttributes() as $key => $attribute) {
                if ($attribute->getIsVisibleOnFront()) {
                    $value = $product[$key];

                    $productAttributes[$id][$key] = [
                        'attribute_value' => $value,
                        'attribute_code' => $attribute->getAttributeCode(),
                        'attribute_type' => $attribute->getFrontendInput(),
                        'attribute_label' => $attribute->getFrontendLabel(),
                        'attribute_id' => $attribute->getAttributeId()
                    ];

                    // Collect attributes only if we need to get options
                    if ($isCollectOptions && !isset($attributes[$key])) {
                        $attributes[$key] = $attribute;

                        // Collect all swatches (we will need additional data for them)
                        /** @var Attribute $attribute */
                        if ($this->swatchHelper->isSwatchAttribute($attribute)) {
                            $swatchAttributes[] = $key;
                        }
                    }
                }
            }
        }

        if (!$isCollectOptions) {
            return $productAttributes;
        }

        $attributeCodes = array_keys($attributes);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.attribute_code', $attributeCodes, 'in')
            ->create();

        /** @var SearchCriteriaInterface $searchCriteria */
        $attributeRepository = $this->attributeRepository->getList($searchCriteria);
        $detailedAttributes = $attributeRepository->getItems();

        // To collect ids of options, to later load swatch data
        $optionIds = [];

        // Loop again, get options sorted in the right places
        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            foreach ($detailedAttributes as $attribute) {
                $key = $attribute->getAttributeCode();
                $options = $attribute->getOptions();
                array_shift($options);

                $productAttributes[$id][$key]['attribute_options'] = [];

                foreach ($options as $option) {
                    $value = $option->getValue();
                    $optionIds[] = $value;

                    $productAttributes[$id][$key]['attribute_options'][$value] = [
                        'value' => $value,
                        'label' => $option->getLabel()
                    ];
                }
            }
        }

        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

        if (empty($swatchAttributes)) {
            return $productAttributes;
        }

        // Loop last time, appending swatches
        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            foreach ($detailedAttributes as $attribute) {
                $key = $attribute->getAttributeCode();

                if (in_array($key, $swatchAttributes)) {
                    $options = $attribute->getOptions();
                    array_shift($options);

                    foreach ($options as $option) {
                        $value = $option->getValue();
                        $swatchOption = $swatchOptions[$value];
                        $productAttributes[$id][$key]['attribute_options'][$value]['swatch_data'] = $swatchOption;
                    }
                }
            }
        }

        return $productAttributes;
    }
}