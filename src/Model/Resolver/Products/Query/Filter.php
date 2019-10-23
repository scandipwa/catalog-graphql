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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Swatches\Helper\Data;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;

/**
 * Retrieve filtered product data based off given search criteria in a format that GraphQL can interpret.
 * Adds support for min and manx price values
 */
class Filter
{
    /**
     * @var SearchResultFactory
     */
    private $searchResultFactory;

    /**
     * @var \ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product
     */
    private $productDataProvider;

    /**
     * @var FieldTranslator
     */
    private $fieldTranslator;

    /**
     * @var Resolver
     */
    private $layerResolver;

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

    /**
     * @param SearchResultFactory $searchResultFactory
     * @param Product $productDataProvider
     * @param Resolver $layerResolver
     * @param FieldTranslator $fieldTranslator
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param Data $swatchHelper
     */
    public function __construct(
        SearchResultFactory $searchResultFactory,
        Product $productDataProvider,
        Resolver $layerResolver,
        FieldTranslator $fieldTranslator,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository,
        Data $swatchHelper
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->searchResultFactory = $searchResultFactory;
        $this->productDataProvider = $productDataProvider;
        $this->fieldTranslator = $fieldTranslator;
        $this->layerResolver = $layerResolver;
        $this->swatchHelper = $swatchHelper;
    }

    /**
     * Filter catalog product data based off given search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param ResolveInfo $info
     * @param bool $isSearch
     * @return SearchResult
     * @throws LocalizedException
     */
    public function getResult(
        SearchCriteriaInterface $searchCriteria,
        ResolveInfo $info,
        bool $isSearch = false
    ): SearchResult {
        $fields = $this->getProductFields($info);
        $products = $this->productDataProvider->getList($searchCriteria, $fields, $isSearch);
        $productArray = [];
        $attributes = [];

        if (in_array('attributes', $fields)) {
            $attributes = $this->getProductAttributes($products);
        }

        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($products->getItems() as $product) {
            $id = $product->getId();

            $productArray[$id] = $product->getData();
            $productArray[$id]['model'] = $product;

            if (isset($attributes[$id])) {
                $productArray[$id]['attributes'] = $attributes[$id];
            }
        }

        return $this->searchResultFactory->create(
            $products->getTotalCount(),
            $this->productDataProvider->getMinPrice(),
            $this->productDataProvider->getMaxPrice(),
            $productArray
        );
    }

    /**
     * @param $products SearchResultsInterface
     * @return array
     */
    protected function getProductAttributes($products) {
        $items = $products->getItems();

        $productAttributes = [];
        $attributes = [];
        $swatchAttributes = [];

        // Collect attributes for request
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($items as $product) {
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

                    if (!isset($attributes[$key])) {
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

        $attributeCodes = array_keys($attributes);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.attribute_code', $attributeCodes, 'in')
            ->create();

        /** @var \Magento\Framework\Api\Search\SearchCriteriaInterface $searchCriteria */
        $attributeRepository = $this->attributeRepository->getList($searchCriteria);
        $detailedAttributes = $attributeRepository->getItems();

        // To collect ids of options, to later load swatch data
        $optionIds = [];

        // Loop again, get options sorted in the right places
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($items as $product) {
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
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($items as $product) {
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

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo $info
     * @return string[]
     */
    private function getProductFields(ResolveInfo $info) : array
    {
        $fieldNames = [];
        foreach ($info->fieldNodes as $node) {
            if ($node->name->value !== 'products') {
                continue;
            }
            foreach ($node->selectionSet->selections as $selection) {
                if ($selection->name->value !== 'items') {
                    continue;
                }

                foreach ($selection->selectionSet->selections as $itemSelection) {
                    if ($itemSelection->kind === 'InlineFragment') {
                        foreach ($itemSelection->selectionSet->selections as $inlineSelection) {
                            if ($inlineSelection->kind === 'InlineFragment') {
                                continue;
                            }
                            $fieldNames[] = $this->fieldTranslator->translate($inlineSelection->name->value);
                        }
                        continue;
                    }
                    $fieldNames[] = $this->fieldTranslator->translate($itemSelection->name->value);
                }
            }
        }

        return $fieldNames;
    }
}
