<?php

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product\MediaGallery;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Image\Placeholder as PlaceholderProvider;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Url
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Product\MediaGallery
 * @deprecated
 */
class Url implements ResolverInterface
{
    /**
     * @var ImageFactory
     */
    protected $productImageFactory;

    /**
     * @var PlaceholderProvider
     */
    protected $placeholderProvider;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ImageFactory $productImageFactory
     * @param PlaceholderProvider $placeholderProvider
     * @param Image $imageHelper
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ImageFactory $productImageFactory,
        PlaceholderProvider $placeholderProvider,
        Image $imageHelper,
        Emulation $emulation,
        StoreManagerInterface $storeManager
    ) {
        $this->productImageFactory = $productImageFactory;
        $this->placeholderProvider = $placeholderProvider;
        $this->imageHelper = $imageHelper;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed|Value
     * @throws Exception
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['image_type']) && !isset($value['file'])) {
            throw new LocalizedException(__('"image_type" value should be specified'));
        }

        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Product $product */
        $product = $value['model'];

        if (isset($value['image_type'])) {
            $storeId = $this->storeManager->getStore()->getId();
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            $image = $this->imageHelper
                ->init(
                    $product,
                    sprintf('scandipwa_%s', $value['image_type']),
                    ['type' => $value['image_type']]
                )
                ->constrainOnly(true)
                ->keepAspectRatio(true)
                ->keepTransparency(true)
                ->keepFrame(false);

            $this->emulation->stopEnvironmentEmulation();

            return $image->getUrl();
        }

        if (isset($value['file'])) {
            $image = $this->productImageFactory->create();
            $image->setDestinationSubdir('image')->setBaseFile($value['file']);
            $imageUrl = $image->getUrl();
            return $imageUrl;
        }

        return [];
    }
}
