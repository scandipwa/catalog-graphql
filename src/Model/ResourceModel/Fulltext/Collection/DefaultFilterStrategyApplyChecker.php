<?php
/**
 * Scandiweb_CatalogGraphQl
 *
 * @category    Scandiweb
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @author      Valerijs Sceglovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Fulltext\Collection;

use Magento\Elasticsearch\Model\ResourceModel\Fulltext\Collection\DefaultFilterStrategyApplyChecker as SourceDefaultFilterStrategyApplyCheckerAlias;

/**
 * Class DefaultFilterStrategyApplyChecker
 * @package ScandiPWA\CatalogGraphQl\Model\ResourceModel\Fulltext\Collection
 */
class DefaultFilterStrategyApplyChecker extends SourceDefaultFilterStrategyApplyCheckerAlias
{
    /**
     * Check if this strategy applicable for current engine.
     *
     * @return bool
     */
    public function isApplicable(): bool
    {
        return true;
    }
}