<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use GraphQL\Language\AST\FieldNode;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\SearchCriteriaInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

/**
 * Retrieve filtered product data based off given search criteria in a format that GraphQL can interpret.
 * Adds support for min and manx price values
 */
class Filter
{
    use ResolveInfoFieldsTrait;

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
     * @var DataPostProcessor
     */
    protected $productPostProcessor;

    /**
     * @param SearchResultFactory $searchResultFactory
     * @param Product $productDataProvider
     * @param Resolver $layerResolver
     * @param FieldTranslator $fieldTranslator
     * @param DataPostProcessor $productPostProcessor
     */
    public function __construct(
        SearchResultFactory $searchResultFactory,
        Product $productDataProvider,
        Resolver $layerResolver,
        FieldTranslator $fieldTranslator,
        DataPostProcessor $productPostProcessor
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
     * @param array $fields
     * @param bool $isSearch
     * @return SearchResult
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getResult(
        SearchCriteriaInterface $searchCriteria,
        ResolveInfo $info,
        array $fields,
        bool $isSearch = false
    ): SearchResult {
        $isReturnCount = in_array('total_count', $fields, true);
        $isReturnItems = in_array('items', $fields, true);
        $isReturnMinMax = count(array_intersect($fields, ['max_price', 'min_price'])) > 0;

        $productFields = $this->getFieldsFromProductInfo($info, 'products/items');

        $products = $this->productDataProvider->getList(
            $searchCriteria,
            $productFields,
            $isSearch,
            false,
            $isReturnMinMax,
            $isReturnCount,
            $isReturnItems
        );

        if ($isReturnItems) {
            $productArray = $this->productPostProcessor->process(
                $products->getItems(),
                'products/items',
                $info
            );
        } else {
            $productArray = array_map(function ($product) {
                return $product->getData() + ['model' => $product];
            }, $products->getItems());
        }

        return $this->searchResultFactory->create(
            [
                'totalCount' => $isReturnCount ? $products->getTotalCount() : 0,
                'productsSearchResult' => $productArray,
                'pageSize' => $searchCriteria->getPageSize(),
                'currentPage' => $searchCriteria->getCurrentPage()
            ]
        );
    }

    /**
     * Take the main info about common field
     *
     * @param FieldNode $node
     * @return array
     */
    protected function getFieldContent($node)
    {
        $fieldNames = [];

        foreach ($node->selectionSet->selections as $itemSelection) {
            if ($itemSelection->kind !== 'InlineFragment') {
                $fieldNames[] = $this->fieldTranslator->translate($itemSelection->name->value);
                continue;
            }

            foreach ($itemSelection->selectionSet->selections as $inlineSelection) {
                if ($inlineSelection->kind === 'InlineFragment') {
                    continue;
                }

                $fieldNames[] = $this->fieldTranslator->translate($inlineSelection->name->value);
            }
        }

        return $fieldNames;
    }
}
