<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */


namespace ScandiPWA\CatalogGraphQl\Model;


use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Query\Fields;
use Magento\Framework\GraphQl\Query\Resolver\Argument\AstConverter;

/**
 * Provide correct attribute list for request parameter validation (attribute)
 * @see AstConverter
 *
 * Class AttributeDbProvider
 * @package ScandiPWA\CatalogGraphQl
 */
class AttributeDbProvider
{
    /**
     * @var ResourceConnection
     */
    private $connection;
    
    /**
     * @var Fields
     */
    private $queryFields;
    
    /**
     * AttributeDbProvider constructor.
     * @param ResourceConnection $connection
     * @param Fields             $queryFields
     */
    public function __construct(
        ResourceConnection $connection,
        Fields $queryFields
    )
    {
        $this->connection = $connection;
        $this->queryFields = $queryFields;
    }
    
    /**
     * Returns array of valid attributes, corresponding to request
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Statement_Exception
     * @return array
     */
    public function getProductAttributes(): array
    {
        $fieldsUsedInQuery = $this->queryFields->getFieldsUsedInQuery();
        $connection = $this->connection->getConnection();
        $placeHolders = str_repeat('?,', count($fieldsUsedInQuery) - 1) . '?';
        $sql = "SELECT eav_attribute.attribute_code
FROM eav_attribute
WHERE eav_attribute.attribute_id IN (
    SELECT catalog_eav_attribute.attribute_id
    FROM catalog_eav_attribute
    WHERE catalog_eav_attribute.is_filterable = 1
) AND eav_attribute.attribute_code IN ($placeHolders)";
        $query = $connection->query($sql, array_keys($fieldsUsedInQuery));

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
}