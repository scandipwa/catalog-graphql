<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Aggregations\DataProvider;

use Magento\Swatches\Helper\Data;

/**
 * Class SwatchData
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class Swatches
{
    /**
     * @var array Array of attribute option IDs to request
     */
    protected $optionIds = [];

    /**
     * @var array Cache of swatch data
     */
    protected $swatchData = [];

    /**
     * @var Data
     */
    protected $swatchHelper;

    /**
     * Attributes constructor.
     * @param Data $swatchHelper
     */
    public function __construct(
        Data $swatchHelper
    ) {
        $this->swatchHelper = $swatchHelper;
    }

    /**
     * @param int $optionId
     */
    public function addAttributeOptionId(int $optionId): void {
        $this->optionIds[] = $optionId;
    }

    /**
     * @return array
     */
    public function getSwatchData(): array {
        if (!count($this->swatchData)) {
            $this->swatchData = $this->swatchHelper->getSwatchesByOptionsId($this->optionIds);
        }

        return $this->swatchData;
    }
}
