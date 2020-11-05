<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedAlias;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use Magento\GroupedProduct\Model\Product\Initialization\Helper\ProductLinks\Plugin\Grouped;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as ProductDataProvider;

use Magento\GroupedProductGraphQl\Model\Resolver\GroupedItems as MagentoGroupedItems;

/**
 * Class ConfigurableVariant
 *
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class GroupedItems extends MagentoGroupedItems
{
    use ResolveInfoFieldsTrait;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var DataPostProcessor
     */
    protected $postProcessor;

    /**
     * @var ProductDataProvider
     */
    protected $productDataProvider;

    /**
     * @var GroupedAlias
     */
    protected $grouped;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $postProcessor,
        ProductDataProvider $productDataProvider,
        Product $productResolver,
        GroupedAlias $grouped
    ) {
        parent::__construct(
            $productResolver
        );

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->postProcessor = $postProcessor;
        $this->productDataProvider = $productDataProvider;
        $this->grouped = $grouped;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $itemData = [];
        $productSKUs = [];
        $productModel = $value['model'];

        // This fix allows to request min / max price and grouped items
        $this->grouped->flushAssociatedProductsCache($productModel);
        $links = $productModel->getProductLinks();

        foreach ($links as $link) {
            /** @var ProductLinkInterface $link */
            if ($link->getLinkType() !== Grouped::TYPE_NAME) {
                continue;
            }

            $productSKU = $link->getLinkedProductSku();

            $itemData[$productSKU] = [
                'position' => (int) $link->getPosition(),
                'qty' => $link->getExtensionAttributes()->getQty(),
                'sku' => $productSKU
            ];

            $productSKUs[] = $productSKU;
        }

        $attributeCodes = $this->getFieldsFromProductInfo($info, 'items/product');

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $productSKUs, 'in')
            ->create();

        $products = $this->productDataProvider
            ->getList(
                $searchCriteria,
                $attributeCodes,
                false,
                true
            )
            ->getItems();

        $productsData = $this->postProcessor->process(
            $products,
            'items/product',
            $info
        );

        foreach ($productsData as $productData) {
            if (!isset($itemData[$productData['sku']])) {
                continue;
            }

            $itemData[$productData['sku']]['product'] = $productData;
        }

        return $itemData;
    }
}
