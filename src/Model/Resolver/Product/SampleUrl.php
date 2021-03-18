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
use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Model\LinkFactory;

/**
 * Class SamplesTitle
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Product
 */
class SampleUrl implements ResolverInterface {

    /**
     * @var LinkFactory
     */
    protected $_linkFactory;

    /**
     * SampleUrl constructor.
     * @param LinkFactory $linkRepository
     * @param Link $link
     */
    public function __construct(
        LinkFactory $linkRepository
    ) {
        $this->_linkFactory = $linkRepository;
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
        $linkId = $value['id'];

        /** @var Link $link */
        $link = $this->_linkFactory->create()->load($linkId);

        return ($link->getSampleFile() || $link->getSampleUrl()) ? $value['sample_url'] : '';
    }
}
