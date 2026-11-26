<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class plateam_partner extends CModule
{
    public $MODULE_ID = 'plateam.partner';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = 'PLATEAM';
    public $PARTNER_URI = 'https://pla.team';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('PLATEAM_PARTNER_MODULE_NAME') ?: 'PLATEAM Partner';
        $this->MODULE_DESCRIPTION = Loc::getMessage('PLATEAM_PARTNER_MODULE_DESC')
            ?: 'Connect Bitrix store to PLATEAM (widget + Partner API)';
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!CheckVersion(ModuleManager::getVersion('main'), '18.0.0')) {
            $APPLICATION->ThrowException(Loc::getMessage('PLATEAM_PARTNER_ERR_MAIN') ?: 'Requires main >= 18.0.0');
            return false;
        }
        if (!ModuleManager::isModuleInstalled('sale')) {
            $APPLICATION->ThrowException(Loc::getMessage('PLATEAM_PARTNER_ERR_SALE') ?: 'Requires sale module');
            return false;
        }
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        return true;
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    public function InstallDB()
    {
        require_once dirname(__DIR__) . '/lib/Config.php';
        require_once dirname(__DIR__) . '/lib/OptionsMigrator.php';
        \Plateam\Partner\OptionsMigrator::migrate();

        if (!\Bitrix\Main\Loader::includeModule('sale')) {
            return false;
        }
        require_once dirname(__DIR__) . '/lib/OrderPropertyInstaller.php';
        \Plateam\Partner\OrderPropertyInstaller::install();
        return true;
    }

    public function UnInstallDB()
    {
        return true;
    }

    public function InstallEvents()
    {
        $em = \Bitrix\Main\EventManager::getInstance();
        $saleHandlers = [
            ['OnSaleOrderBeforeSaved', 'onOrderBeforeSaved'],
            ['OnSaleOrderSaved', 'onOrderSaved'],
            ['OnSalePaymentEntitySaved', 'onPaymentSaved'],
            ['OnSaleOrderPaid', 'onOrderPaid'],
            ['OnSaleOrderCanceled', 'onOrderCanceled'],
        ];
        foreach ($saleHandlers as [$event, $method]) {
            $em->registerEventHandler(
                'sale',
                $event,
                $this->MODULE_ID,
                '\\Plateam\\Partner\\EventHandlers',
                $method
            );
        }
        $em->registerEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            '\\Plateam\\Partner\\PromoBridge',
            'onProlog'
        );
        $em->registerEventHandler(
            'sale',
            'OnSaleDiscountCouponApplyed',
            $this->MODULE_ID,
            '\\Plateam\\Partner\\PromoBridge',
            'onCouponApplied'
        );

        return true;
    }

    public function UnInstallEvents()
    {
        $em = \Bitrix\Main\EventManager::getInstance();
        $saleHandlers = [
            ['OnSaleOrderBeforeSaved', 'onOrderBeforeSaved'],
            ['OnSaleOrderSaved', 'onOrderSaved'],
            ['OnSalePaymentEntitySaved', 'onPaymentSaved'],
            ['OnSaleOrderPaid', 'onOrderPaid'],
            ['OnSaleOrderCanceled', 'onOrderCanceled'],
        ];
        foreach ($saleHandlers as [$event, $method]) {
            $em->unRegisterEventHandler(
                'sale',
                $event,
                $this->MODULE_ID,
                '\\Plateam\\Partner\\EventHandlers',
                $method
            );
        }
        $em->unRegisterEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            '\\Plateam\\Partner\\PromoBridge',
            'onProlog'
        );
        $em->unRegisterEventHandler(
            'sale',
            'OnSaleDiscountCouponApplyed',
            $this->MODULE_ID,
            '\\Plateam\\Partner\\PromoBridge',
            'onCouponApplied'
        );

        return true;
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            __DIR__ . '/components',
            $_SERVER['DOCUMENT_ROOT'] . '/local/components',
            true,
            true
        );
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx('/local/components/plateam');
        return true;
    }
}
