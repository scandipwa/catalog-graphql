<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Adds passed in attributes to product collection results
 *
 * {@inheritdoc}
 */
class ImagesProcessor implements CollectionProcessorInterface
{
    const IMAGE_FIELDS = ['thumbnail', 'small_image', 'image'];

    /**
     * @inheritdoc
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames
    ): Collection {
        if (array_intersect(self::IMAGE_FIELDS, $attributeNames)) {
            $imagesToBeRequested = [];

            foreach (self::IMAGE_FIELDS as $imageType) {
                if (isset($attributesFlipped[$imageType])) {
                    $imagesToBeRequested[] = $imageType;
                    $imagesToBeRequested[] = sprintf('%s_label', $imageType);
                }
            }

            $collection->addAttributeToSelect($imagesToBeRequested);
        }

        return $collection;
    }
}
