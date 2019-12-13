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

use Magento\Framework\App\Area;
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
    protected $valueFactory;

    /**
     * @var HelperFactory
     */
    protected $helperFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Emulation
     */
    protected $emulation;

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
    ) {
        $this->valueFactory = $valueFactory;
        $this->storeManager = $storeManager;
        $this->helperFactory = $helperFactory;
        $this->emulation = $emulation;
    }

    protected function getImageOfType(
        $mediaGalleryEntry,
        $imageId,
        $type
    ) {
        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        $image = $this->helperFactory->init($mediaGalleryEntry, $imageId, ['type' => $type])
            ->setImageFile($mediaGalleryEntry->getData('file'))
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        $this->emulation->stopEnvironmentEmulation();

        return [
            'url' => $image->getUrl(),
            'type' => $type
        ];
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
                $thumbnail = $this->getImageOfType($entry, 'scandipwa_media_thumbnail', 'thumbnail');
                $base = $this->getImageOfType($entry, 'scandipwa_media_base', 'base');
                $mediaGalleryEntries[$key] = $entry->getData() + ['thumbnail' => $thumbnail, 'base' => $base];

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
