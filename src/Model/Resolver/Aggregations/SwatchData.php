<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product as ProductDataProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\FieldTranslator;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations\DataProvider\Swatches;

/**
 * Class SwatchData
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class SwatchData implements ResolverInterface
{
    /** @var ValueFactory  */
    protected $valueFactory;

    /** @var FieldTranslator  */
    protected $fieldTranslator;

    /** @var Swatches  */
    protected $swatches;

    /**
     * @param Swatches $swatches
     * @param ValueFactory $valueFactory
     * @param FieldTranslator $fieldTranslator
     */
    public function __construct(
        Swatches $swatches,
        ValueFactory $valueFactory,
        FieldTranslator $fieldTranslator
    ) {
        $this->swatches = $swatches;
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
        $optionId = $value['value'];

        if (is_int($optionId)) {
            $this->swatches->addAttributeOptionId($optionId);
        }

        $result = function () use ($optionId) {
            $swatches = $this->swatches->getSwatchData();
            return $swatches[$optionId] ?? null;
        };

        return $this->valueFactory->create($result);
    }
}
