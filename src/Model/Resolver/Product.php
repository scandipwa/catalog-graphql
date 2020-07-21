<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product as ProductDataProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * @inheritdoc
 */
class Product implements ResolverInterface
{
    /**
     * @var ProductDataProvider
     */
    protected $productDataProvider;

    /**
     * @var ValueFactory
     */
    protected $valueFactory;

    /**
     * @var FieldTranslator
     */
    protected $fieldTranslator;

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
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['sku'])) {
            throw new GraphQlInputException(__('No child sku found for product link.'));
        }

        $result = function () use ($value) {
            if (empty($value['product'])) {
                return null;
            }

            return $value['product'];
        };

        return $this->valueFactory->create($result);
    }
}
