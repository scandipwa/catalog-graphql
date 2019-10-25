<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product as ProductDataProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use ScandiPWA\CatalogGraphQl\Helper\Attributes;
use ScandiPWA\CatalogGraphQl\Helper\Images;

/**
 * @inheritdoc
 */
class Product implements ResolverInterface
{
    /**
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var FieldTranslator
     */
    private $fieldTranslator;


    /**
     * @param ProductDataProvider $productDataProvider
     * @param ValueFactory $valueFactory
     * @param FieldTranslator $fieldTranslator
     */
    public function __construct(
        ProductDataProvider $productDataProvider,
        ValueFactory $valueFactory,
        FieldTranslator $fieldTranslator
    ) {
        $this->productDataProvider = $productDataProvider;
        $this->valueFactory = $valueFactory;
        $this->fieldTranslator = $fieldTranslator;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['sku'])) {
            throw new GraphQlInputException(__('No child sku found for product link.'));
        }

        $result = function () use ($value) {
            if (empty($value['product'])) {
                return null;
            }

            $product = $value['product'];
            $data = array_merge($product['model']->getData(), $product);

            return $data;
        };

        return $this->valueFactory->create($result);
    }
}
