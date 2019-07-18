<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin\Resolver\Products\FilterArgument;

use \Magento\CatalogGraphQl\Model\Resolver\Products\FilterArgument\ProductEntityAttributesForAst
    as MagentoProductEntityAttributesForAst;
use ScandiPWA\CatalogGraphQl\Model\AttributeDbProvider;

class ProductEntityAttributesForAst
{
    /**
     * ProductEntityAttributesForAst constructor.
     * @param array $attributes
     */
    public function __construct(
        AttributeDbProvider $productAttributeProvider,
        array $attributes
    )
    {
        $this->additionalAttributes = $attributes;
        $this->productAttributeProvider = $productAttributeProvider;
    }

    /**
     * Injects custom additional attributes
     *
     * @param MagentoProductEntityAttributesForAst $subject
     * @param                                                                                              $result
     *
     * @return array
     */
    public function aroundGetEntityAttributes(MagentoProductEntityAttributesForAst $subject, callable $next) {
        return array_merge($next(),
            $this->productAttributeProvider->getProductAttributes(),
            array_keys($this->additionalAttributes));
    }
}
