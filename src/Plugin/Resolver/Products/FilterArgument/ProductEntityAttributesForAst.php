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
use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\CatalogGraphQl\Model\AttributeDbProvider;
use Zend_Db_Adapter_Exception;
use Zend_Db_Statement_Exception;

class ProductEntityAttributesForAst
{
    /**
     * @var array
     */
    protected $additionalAttributes;

    /**
     * @var AttributeDbProvider
     */
    protected $productAttributeProvider;

    /**
     * ProductEntityAttributesForAst constructor.
     * @param AttributeDbProvider $productAttributeProvider
     * @param array $attributes
     */
    public function __construct(
        AttributeDbProvider $productAttributeProvider,
        array $attributes
    ) {
        $this->additionalAttributes = $attributes;
        $this->productAttributeProvider = $productAttributeProvider;
    }

    /**
     * Injects custom additional attributes
     *
     * @param MagentoProductEntityAttributesForAst $subject
     * @param callable $next
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function aroundGetEntityAttributes(
        MagentoProductEntityAttributesForAst $subject,
        callable $next
    ) {
        return array_merge(
            $next(),
            $this->productAttributeProvider->getProductAttributes(),
            array_keys($this->additionalAttributes)
        );
    }
}
