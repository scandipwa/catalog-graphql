<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query;

use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;

/**
 * Category UID processor class for category uid and category id arguments
 */
class CategoryUidArgsProcessor implements ArgumentsProcessorInterface
{
    protected const ID = 'category_id';
    protected const UID = 'category_uid';

    protected Uid $uidEncoder;

    /**
     * @param Uid $uidEncoder
     */
    public function __construct(Uid $uidEncoder)
    {
        $this->uidEncoder = $uidEncoder;
    }

    /**
     * Override to enable both category_id and category_uid to be used at the same time
     *
     * @param string $fieldName
     * @param array $args
     * @return array
     * @throws GraphQlInputException
     */
    public function process(
        string $fieldName,
        array $args
    ): array {
        $idFilter = $args['filter'][self::ID] ?? [];
        $uidFilter = $args['filter'][self::UID] ?? [];

        if (empty($uidFilter)) {
            return $args;
        }

        if (isset($uidFilter['eq'])) {
            $args['filter'][self::ID]['eq'] = $this->uidEncoder->decode((string) $uidFilter['eq']);
        } elseif (!empty($uidFilter['in'])) {
            foreach ($uidFilter['in'] as $uid) {
                $args['filter'][self::ID]['in'][] = $this->uidEncoder->decode((string) $uid);
            }

            unset($args['filter'][self::ID]['eq']);
        }

        unset($args['filter'][self::UID]);
    }
}
