<?php
/**
 * Scandiweb_CatalogGraphQl
 *
 * @category    Scandiweb
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @author      Aleksandrs Mokans <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;

/**
 * Class InitGraphQlTranslations
 * @package ScandiPWA\CatalogGraphQl\Plugin
 */
class InitGraphQlTranslations
{
    /**
     * Application
     *
     * @var AreaList
     */
    protected $areaList;

    /**
     * State
     *
     * @var State
     */
    protected $appState;

    /**
     * @param AreaList $areaList
     * @param State $appState
     */
    public function __construct(
        AreaList $areaList,
        State $appState
    ) {
        $this->areaList = $areaList;
        $this->appState = $appState;
    }

    /**
     * Initialize translation area part
     * Similarly to how SOAP and REST controllers initialize translation area part
     * Or how frontend abstract action plugin loads design that initializes the same part
     * For frontend-specific areas, are emulation methods later would switch translation data as necessary
     * However, emulation does not initialize translation area part,
     * and as it is never called for graphQl, it is never loaded without such a plugin
     *
     * @param FrontControllerInterface $subject
     * @param RequestInterface $request
     *
     * @return void
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(
        FrontControllerInterface $subject,
        RequestInterface $request
    ) {
        $area = $this->areaList->getArea($this->appState->getAreaCode());
        if ($area) {
            $area->load(Area::PART_TRANSLATE);
        }
    }
}