<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Dmitrijs Voronovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 * @license     OSL-3.0
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;

/**
 * Resolve data for product canonical URL
 */
class CanonicalUrl implements ResolverInterface
{
    /**
     * URL Model instance
     *
     * @var UrlInterface
     */
    protected $_url;

    /**
     * CanonicalUrl constructor.
     * @param UrlInterface $url
     */
    public function __construct(
        UrlInterface $url
    ) {
        $this->_url = $url;
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

        /* @var $product Product */
        $product = $value['model'];

        return $this->_url->getUrl('product/') . $product->getUrlKey();
    }
}
