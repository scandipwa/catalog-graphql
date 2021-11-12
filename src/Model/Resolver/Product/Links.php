<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\DownloadableGraphQl\Model\ConvertLinksToArray;
use Magento\DownloadableGraphQl\Model\GetDownloadableProductLinks;
use Magento\DownloadableGraphQl\Resolver\Product\Links as SourceLinks;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Links extends SourceLinks
{
    /**
     * @var PriceHelper
     */
    protected $pricingHelper;

    /**
     * @param GetDownloadableProductLinks $getDownloadableProductLinks
     * @param ConvertLinksToArray $convertLinksToArray
     * @param PriceHelper $pricingHelper
     */
    public function __construct(
        GetDownloadableProductLinks $getDownloadableProductLinks,
        ConvertLinksToArray $convertLinksToArray,
        PriceHelper $pricingHelper
    ) {
        parent::__construct($getDownloadableProductLinks, $convertLinksToArray);
        $this->pricingHelper = $pricingHelper;
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

        /** @var Product $product */
        $product = $value['model'];
        $data = parent::resolve($field, $context, $info, $value, $args);

        foreach ($data as &$link) {
            $link['price'] = $this->pricingHelper->currencyByStore(
                $link['price'], $product->getStore(), false
            );
        }

        return $data;
    }
}
