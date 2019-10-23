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

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use \Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Swatches\Helper\Data;
use Magento\Framework\Api\Search\SearchCriteriaInterface;

class AttributesWithValue implements ResolverInterface
{
    /**
     * @var Data
     */
    protected $swatchHelper;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * CustomAttributes constructor.
     * @param Data $swatchHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductAttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Data $swatchHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
    }

    protected function appendOptions(&$attrs) {
        $attributeCodes = array_keys($attrs);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.attribute_code', $attributeCodes, 'in')
            ->create();

        /** @var SearchCriteriaInterface $searchCriteria */
        $attributeRepository = $this->attributeRepository->getList($searchCriteria);
        $option_values = [];
        $option_ids = [];

        foreach ($attributeRepository->getItems() as $attribute) {
            $code = $attribute->getAttributeCode();
            $options = $attribute->getOptions();
            array_shift($options);
            $option_values[$code] = [];

            foreach ($options as $option) {
                $value = $option->getValue();
                $option_ids[] = $value;
                $option_values[$code][$value] = [
                    'value' => $option->getValue(),
                    'label' => $option->getLabel()
                ];
            }
        }

        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($option_ids);

        foreach ($attributeRepository->getItems() as $attribute) {
            $code = $attribute->getAttributeCode();
            $options = $attribute->getOptions();
            array_shift($options);

            $attrs[$code]['attribute_options'] = $option_values[$code];

            foreach ($options as $option) {
                $value = $option->getValue();

                if (isset($option_values[$code][$value], $attrs[$code]['attribute_options'][$value])) {
                    $attrs[$code]['attribute_options'][$value]['swatch_data'] = $swatchOptions[$value];
                }
            }
        }
    }

    function array_values_recursive($arr)
    {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = $this->array_values_recursive($value);
            }
        }

        return $arr;
    }

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo $info
     * @return string[]
     */
    protected function getAttributeFields(ResolveInfo $info) : array
    {
        $fieldNames = [];

        foreach ($info->fieldNodes as $node) {
            if ($node->name->value !== 'attributes') {
                continue;
            }

            foreach ($node->selectionSet->selections as $itemSelection) {
                $fieldNames[] = $itemSelection->name->value;
            }
        }

        return $fieldNames;
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

        $requestedFields = $this->getAttributeFields($info);

        foreach ($product->getAttributes() as $attr) {
            if ($attr->getIsVisibleOnFront()) {
                $code = $attr->getAttributeCode();
                $value = $product[$code];

                $attributesToReturn[$code] = [
                    'attribute_value' => $value,
                    'attribute_code' => $attr->getAttributeCode(),
                    'attribute_type' => $attr->getFrontendInput(),
                    'attribute_label' => $attr->getFrontendLabel(),
                    'attribute_id' => $attr->getAttributeId()
                ];
            }
        }

        if (in_array('attribute_options', $requestedFields)) {
            $this->appendOptions($attributesToReturn);
        }

        $return = $this->array_values_recursive($attributesToReturn);

        return $return;
    }
}