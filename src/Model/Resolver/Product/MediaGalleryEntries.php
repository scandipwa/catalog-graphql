<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as HelperFactory;
use Magento\Store\Model\App\Emulation;

/**
 * Format a product's media gallery information to conform to GraphQL schema representation
 */
class MediaGalleryEntries implements ResolverInterface
{
    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var HelperFactory
     */
    private $helperFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * MediaGalleryEntries constructor.
     * @param ValueFactory $valueFactory
     * @param StoreManagerInterface $storeManager
     * @param HelperFactory $helperFactory
     * @param Emulation $emulation
     */
    public function __construct(
        ValueFactory $valueFactory,
        StoreManagerInterface $storeManager,
        HelperFactory $helperFactory,
        Emulation $emulation
    )
    {
        $this->valueFactory = $valueFactory;
        $this->storeManager = $storeManager;
        $this->helperFactory = $helperFactory;
        $this->emulation = $emulation;
    }

    /**
     * Format product's media gallery entry data to conform to GraphQL schema
     *
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): Value {
        if (!isset($value['model'])) {
            $result = function () {
                return null;
            };
            return $this->valueFactory->create($result);
        }

        /** @var Product $product */
        $product = $value['model'];

        $mediaGalleryEntries = [];
        if (!empty($product->getMediaGalleryEntries())) {
            foreach ($product->getMediaGalleryEntries() as $key => $entry) {
                $storeId = $this->storeManager->getStore()->getId();
                $this->emulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

                $image = $this->helperFactory->init($entry, "product_media_thumbnail", ["type" => "thumbnail"])
                    ->setImageFile($entry->getData("file"))
                    ->constrainOnly(true)
                    ->keepAspectRatio(true)
                    ->keepTransparency(true)
                    ->keepFrame(false);

                $imageSizeInfo = $image->getResizedImageInfo();

                $this->emulation->stopEnvironmentEmulation();

                $thumbnail = [
                    'url' => $image->getUrl(),
                    'type' => 'thumbnail',
                    'width' => $imageSizeInfo[0],
                    'height' => $imageSizeInfo[1],
                ];

                $entryData = $entry->getData();

                $entryData['thumbnail'] = $thumbnail;

                $mediaGalleryEntries[$key] = $entryData;
                if ($entry->getExtensionAttributes() && $entry->getExtensionAttributes()->getVideoContent()) {
                    $mediaGalleryEntries[$key]['video_content']
                        = $entry->getExtensionAttributes()->getVideoContent()->getData();
                }
            }
        }

        $result = function () use ($mediaGalleryEntries) {
            return $mediaGalleryEntries;
        };

        return $this->valueFactory->create($result);
    }
}
