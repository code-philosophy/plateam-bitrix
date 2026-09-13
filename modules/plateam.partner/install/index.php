<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Plateam\Partner\OrderPropertyInstaller;

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
        $this->MODULE_NAME = 'PLATEAM Partner';
        $this->MODULE_DESCRIPTION = 'Интеграция интернет-магазина с PLATEAM (виджет + Partner API v0)';
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!CheckVersion(ModuleManager::getVersion('main'), '20.0.0')) {
            $APPLICATION->ThrowException('Требуется main >= 20.0.0');
            return false;
        }
        if (!ModuleManager::isModuleInstalled('sale')) {
            $APPLICATION->ThrowException('Требуется модуль sale');
            return false;
        }
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();
        return true;
    }

    public function DoUninstall()
    {
        $this->UnInstallEvents();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    public function InstallDB()
    {
        if (!\Bitrix\Main\Loader::includeModule('sale')) {
            return false;
        }
        OrderPropertyInstaller::install();
        return true;
    }

    public function UnInstallDB()
    {
        return true;
    }

    public function InstallEvents()
    {
        return true;
    }

    public function UnInstallEvents()
    {
        return true;
    }
}
