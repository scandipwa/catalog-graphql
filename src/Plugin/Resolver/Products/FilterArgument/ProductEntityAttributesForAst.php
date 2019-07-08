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

class ProductEntityAttributesForAst
{
    /**
     * ProductEntityAttributesForAst constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes)
    {
        $this->additionalAttributes = $attributes;
    }

    /**
     * Injects custom additional attributes
     *
     * @param MagentoProductEntityAttributesForAst $subject
     * @param                                                                                              $result
     *
     * @return array
     */
    public function afterGetEntityAttributes(MagentoProductEntityAttributesForAst $subject, $result) {
        $result = array_merge($result, $this->additionalAttributes);
        
        return $result;
    }
}
