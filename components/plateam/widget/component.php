<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Plateam\Partner\Config;

if (!Loader::includeModule('plateam.partner')) {
    return;
}

$arResult['PARTNER_CODE'] = Config::partnerCode();
$arResult['PLATFORM_ORIGIN'] = Config::platformOrigin();
$arResult['WIDGET_VERSION'] = Config::widgetVersion();

$this->includeComponentTemplate();
