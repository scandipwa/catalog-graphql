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

use GraphQL\Language\AST\FieldNode;
use Magento\Framework\App\Helper\AbstractHelper as CoreAbstractHelper;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

abstract class AbstractHelper extends CoreAbstractHelper
{
    /**
     * Take the main info about common field
     *
     * @param $node
     * @return FieldNode|null
     */
    protected function getFieldContent($node)
    {
        return null;
    }

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo $info
     * @return array
     */
    protected function getFieldsFromProductInfo($info)
    {
        $fields = [];

        $nodes = isset($info->fieldNodes) ?
            $info->fieldNodes :
            $info->selectionSet->selections;

        foreach ($nodes as $node) {
            if (!isset($node->name)) {
                continue;
            };

            if ($node->name->value !== 'products'
                && $node->name->value !== 'variants'
                && $node->name->value !== 'items') {
                continue;
            }

            /** @var FieldNode $selection */
            foreach ($node->selectionSet->selections as $selection) {
                if (!isset($selection->name)) {
                    continue;
                }

                if ($selection->name->value !== 'items'
                    && $selection->name->value !== 'product') {
                    continue;
                }

                $fields = $this->getFieldContent($selection);
                break;
            }
        }

        return $fields;
    }
}
