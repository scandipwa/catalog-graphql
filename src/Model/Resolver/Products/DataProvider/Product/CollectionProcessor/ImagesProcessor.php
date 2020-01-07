<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
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
    /**
     * @var MediaConfig
     */
    protected $mediaConfig;

    /**
     * ImagesProcessor constructor.
     * @param MediaConfig $mediaConfig
     */
    public function __construct(
        MediaConfig $mediaConfig
    ) {
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * @inheritdoc
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames
    ): Collection {
        $mediaAttributes = $this->mediaConfig->getMediaAttributeCodes();

        if (array_intersect($mediaAttributes, $attributeNames)) {
            $imagesToBeRequested = [];

            foreach ($mediaAttributes as $imageType) {
                if (isset($attributeNames[$imageType])) {
                    $imagesToBeRequested[] = $imageType;
                    $imagesToBeRequested[] = sprintf('%s_label', $imageType);
                }
            }

            $collection->addAttributeToSelect($imagesToBeRequested);
        }

        return $collection;
    }
}
