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

namespace ScandiPWA\CatalogGraphQl\Model\Context;

use Magento\GraphQl\Model\Query\ContextParametersInterface;
use Magento\GraphQl\Model\Query\ContextParametersProcessorInterface;

/**
 * @inheritdoc
 */
class AddSearchCriteriaToContext implements ContextParametersProcessorInterface
{
    /**
     * @inheritdoc
     */
    public function execute(
        ContextParametersInterface $contextParameters
    ) : ContextParametersInterface {

        $contextParameters->addExtensionAttribute('search_criteria', null);

        return $contextParameters;
    }
}
