<?php

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Catalog\Model\Category\FileInfo;

use Magento\CatalogGraphQl\Model\Resolver\Category\Image as CoreImage;

/**
 * Class Image
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Category
 */
class Image extends CoreImage {
    /** @var FileInfo  */
    protected $fileInfo;

    /** @var DirectoryList  */
    protected $directoryList;

    /**
     * @param DirectoryList $directoryList
     * @param FileInfo $fileInfo
     */
    public function __construct(
        DirectoryList $directoryList,
        FileInfo $fileInfo
    ) {
        parent::__construct(
            $directoryList,
            $fileInfo
        );

        $this->directoryList = $directoryList;
        $this->fileInfo = $fileInfo;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return string|null
     * @throws LocalizedException
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

        /** @var Category $category */
        $category = $value['model'];
        $imagePath = $category->getData('image');
        if (empty($imagePath)) {
            return null;
        }

        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();
        $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_DIRECT_LINK);

        $filenameWithMedia =  $this->fileInfo->isBeginsWithMediaDirectoryPath($imagePath)
            ? $imagePath : $this->formatFileNameWithMediaCategoryFolder($imagePath);

        // return full url
        return rtrim($baseUrl, '/') . $filenameWithMedia;
    }

    /**
     * Format category media folder to filename
     *
     * @param string $fileName
     * @return string
     */
    protected function formatFileNameWithMediaCategoryFolder(string $fileName): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $baseFileName = basename($fileName);

        return '/'
            . $this->directoryList->getUrlPath('media')
            . '/'
            . ltrim(FileInfo::ENTITY_MEDIA_PATH, '/')
            . '/'
            . $baseFileName;
    }
}
