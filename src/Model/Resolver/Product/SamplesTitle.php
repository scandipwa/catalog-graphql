<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\ScopeInterface;
use Magento\Downloadable\Model\Sample;
use Magento\Framework\App\Config\ScopeConfigInterface;


/**
 * Class SamplesTitle
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Product
 */
class SamplesTitle implements ResolverInterface {

    const TYPE_DOWNLOADABLE = 'downloadable';

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * SamplesTitle constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
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

        /** @var Product $product */
        $product = $value['model'];

        return $product->getId() && $product->getTypeId() === self::TYPE_DOWNLOADABLE ?
            $product->getSamplesTitle() :
            $this->_scopeConfig->getValue(
                Sample::XML_PATH_SAMPLES_TITLE,
                ScopeInterface::SCOPE_STORE
            );
    }
}
