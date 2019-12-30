<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
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
use ScandiPWA\Performance\Model\Resolver\ProductPostProcessor;

/**
 * Retrieve filtered product data based off given search criteria in a format that GraphQL can interpret.
 * Adds support for min and manx price values
 */
class Filter
{
    /**
     * @var SearchResultFactory
     */
    protected $searchResultFactory;

    /**
     * @var Product
     */
    protected $productDataProvider;

    /**
     * @var FieldTranslator
     */
    protected $fieldTranslator;

    /**
     * @var Resolver
     */
    protected $layerResolver;

    /**
     * @var ProductPostProcessor
     */
    protected $productPostProcessor;

    /**
     * @param SearchResultFactory $searchResultFactory
     * @param Product $productDataProvider
     * @param Resolver $layerResolver
     * @param FieldTranslator $fieldTranslator
     */
    public function __construct(
        SearchResultFactory $searchResultFactory,
        Product $productDataProvider,
        Resolver $layerResolver,
        FieldTranslator $fieldTranslator,
        ProductPostProcessor $productPostProcessor
    ) {
        $this->searchResultFactory = $searchResultFactory;
        $this->productDataProvider = $productDataProvider;
        $this->fieldTranslator = $fieldTranslator;
        $this->layerResolver = $layerResolver;
        $this->productPostProcessor = $productPostProcessor;
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

        $productArray = $this->productPostProcessor->process(
            $products->getItems(),
            'products/items',
            $info
        );

        return $this->searchResultFactory->create(
            $products->getTotalCount(),
            $this->productDataProvider->getMinPrice(),
            $this->productDataProvider->getMaxPrice(),
            $productArray
        );
    }

    // phpcs:disable
    // Disabling, logic bellow is taken from original M2 class

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo $info
     * @return string[]
     */
    protected function getProductFields(
        ResolveInfo $info
    ): array {
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

    // phpcs:enable
}
