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
     * @var array
     */
    private $additionalAttributes = ['category_url_key', 'category_url_path'];

    /**
     * Injects custom additional attributes
     *
     * @param MagentoProductEntityAttributesForAst $subject
     * @param                                                                                              $result
     *
     * @return array
     */
    public function afterGetEntityAttributes(MagentoProductEntityAttributesForAst $subject, $result) {
        foreach ($this->additionalAttributes as $attribute) {
            $result[$attribute] = $attribute;
        }

        return $result;
    }
}
