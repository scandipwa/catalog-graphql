<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Helper;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Image\Placeholder as PlaceholderProvider;
use Magento\Framework\App\Helper\Context;

class Images extends AbstractHelper
{
    /**
     * @var ImageFactory
     */
    private $productImageFactory;
    /**
     * @var PlaceholderProvider
     */
    private $placeholderProvider;

    /**
     * Images constructor.
     * @param Context $context
     * @param ImageFactory $productImageFactory
     * @param PlaceholderProvider $placeholderProvider
     */
    public function __construct(
        Context $context,
        ImageFactory $productImageFactory,
        PlaceholderProvider $placeholderProvider
    ) {
        parent::__construct($context);

        $this->productImageFactory = $productImageFactory;
        $this->placeholderProvider = $placeholderProvider;
    }

    /**
     * @param $node
     * @return string[]
     */
    protected function getFieldContent($node)
    {
        $images = [];
        $validFields = [
            'image',
            'small_image',
            'thumbnail'
        ];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            };

            $name = $selection->name->value;
            if (in_array($name, $validFields)) {
                $images[] = $name;
                break;
            }
        }

        return $images;
    }

    public function getProductImages($products, $info)
    {
        $fields = $this->getFieldsFromProductInfo($info);
        $productImages = [];

        if (!count($fields)) {
            return [];
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();
            $productImages[$id] = [];

            foreach ($fields as $field) {
                $productImages[$id][$field] = [];
                $image = $product->getData($field);

                if (!$image) {
                    continue;
                };

                $productImages[$id][$field] = [
                    'path' => $image,
                    'url' => $this->getImageUrl($field, $image)
                    // TODO: Find efficient way to get image label
                ];
            }
        }

        return $productImages;
    }

    /**
     * Get image URL
     *
     * @param string $imageType
     * @param string|null $imagePath
     * @return string
     * @throws Exception
     */
    private function getImageUrl(string $imageType, ?string $imagePath): string
    {
        $image = $this->productImageFactory->create();
        $image->setDestinationSubdir($imageType)
            ->setBaseFile($imagePath);

        if ($image->isBaseFilePlaceholder()) {
            return $this->placeholderProvider->getPlaceholder($imageType);
        }

        return $image->getUrl();
    }
}