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

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\SearchCriteriaInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use ScandiPWA\CatalogGraphQl\Helper\Attributes;
use ScandiPWA\CatalogGraphQl\Helper\Images;
use ScandiPWA\CatalogGraphQl\Helper\Stocks;

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
     * @var Product
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
     * @var Attributes
     */
    protected $attributes;

    /**
     * @var Images
     */
    protected $images;

    /**
     * @var Stocks
     */
    protected $stocks;

    /**
     * @param SearchResultFactory $searchResultFactory
     * @param Product $productDataProvider
     * @param Resolver $layerResolver
     * @param FieldTranslator $fieldTranslator
     * @param Attributes $attributes
     * @param Images $images
     * @param Stocks $stocks
     */
    public function __construct(
        SearchResultFactory $searchResultFactory,
        Product $productDataProvider,
        Resolver $layerResolver,
        FieldTranslator $fieldTranslator,
        Attributes $attributes,
        Images $images,
        Stocks $stocks
    ) {
        $this->searchResultFactory = $searchResultFactory;
        $this->productDataProvider = $productDataProvider;
        $this->fieldTranslator = $fieldTranslator;
        $this->layerResolver = $layerResolver;
        $this->attributes = $attributes;
        $this->images = $images;
        $this->stocks = $stocks;
    }

    /**
     * Filter catalog product data based off given search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param ResolveInfo $info
     * @param bool $isSearch
     * @return SearchResult
     * @throws LocalizedException
     * @throws LocalizedException
     */
    public function getResult(
        SearchCriteriaInterface $searchCriteria,
        ResolveInfo $info,
        bool $isSearch = false
    ): SearchResult {
        $fields = $this->getProductFields($info);
        $products = $this->productDataProvider->getList($searchCriteria, $fields, $isSearch);
        $items = $products->getItems();
        $attributes = $this->attributes->getProductAttributes($items, $info);
        $images = $this->images->getProductImages($items, $info);
        $stocks = $this->stocks->getProductStocks($items, $info);
        $productArray = [];

        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($items as $product) {
            $id = $product->getId();

            $productArray[$id] = $product->getData();
            $productArray[$id]['model'] = $product;

            if (isset($images[$id])) {
                foreach ($images[$id] as $imageType => $imageData) {
                    $productArray[$id][$imageType] = $imageData;
                }
            }

            if (isset($stocks[$id])) {
                foreach ($stocks[$id] as $stockType => $stockData) {
                    $productArray[$id][$stockType] = $stockData;
                }
            }

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
