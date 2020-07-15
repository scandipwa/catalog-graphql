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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Links\Collection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * Class BundleItemLinks
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class BundleItemLinks implements ResolverInterface
{
    /** @var Collection */
    protected $linkCollection;

    /** @var ValueFactory */
    protected $valueFactory;

    /**
     * BundleItemLinks constructor.
     *
     * @param Collection $linkCollection
     * @param ValueFactory $valueFactory
     */
    public function __construct(
        Collection $linkCollection,
        ValueFactory $valueFactory
    ) {
        $this->linkCollection = $linkCollection;
        $this->valueFactory = $valueFactory;
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
        if (!isset($value['option_id'], $value['parent_id'])) {
            throw new LocalizedException(__('"option_id" and "parent_id" values should be specified'));
        }

        $this->linkCollection->addIdFilters((int)$value['option_id'], (int)$value['parent_id']);

        $result = function () use ($value, $info) {
            $this->linkCollection->addResolveInfo($info);

            return $this->linkCollection->getLinksForOptionId((int)$value['option_id']);
        };

        return $this->valueFactory->create($result);
    }
}
