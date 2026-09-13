<?php

use Bitrix\Main\EventManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

require_once __DIR__ . '/lib/Config.php';
require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/HoldService.php';
require_once __DIR__ . '/lib/OrderSync.php';
require_once __DIR__ . '/lib/ReturnNotify.php';
require_once __DIR__ . '/lib/SessionBridge.php';
require_once __DIR__ . '/lib/OrderPropertyInstaller.php';
require_once __DIR__ . '/lib/EventHandlers.php';
require_once __DIR__ . '/lib/BasketTotal.php';
require_once __DIR__ . '/lib/OrderDiscount.php';
require_once __DIR__ . '/lib/PromoBridge.php';

Bitrix\Main\Loader::registerAutoLoadClasses('plateam.partner', [
    'Plateam\\Partner\\Config' => 'lib/Config.php',
    'Plateam\\Partner\\ApiClient' => 'lib/ApiClient.php',
    'Plateam\\Partner\\HoldService' => 'lib/HoldService.php',
    'Plateam\\Partner\\OrderSync' => 'lib/OrderSync.php',
    'Plateam\\Partner\\ReturnNotify' => 'lib/ReturnNotify.php',
    'Plateam\\Partner\\SessionBridge' => 'lib/SessionBridge.php',
    'Plateam\\Partner\\OrderPropertyInstaller' => 'lib/OrderPropertyInstaller.php',
    'Plateam\\Partner\\EventHandlers' => 'lib/EventHandlers.php',
    'Plateam\\Partner\\BasketTotal' => 'lib/BasketTotal.php',
    'Plateam\\Partner\\OrderDiscount' => 'lib/OrderDiscount.php',
    'Plateam\\Partner\\PromoBridge' => 'lib/PromoBridge.php',
]);

$eventManager = EventManager::getInstance();
$eventManager->addEventHandler('sale', 'OnSaleOrderBeforeSaved', ['Plateam\\Partner\\EventHandlers', 'onOrderBeforeSaved']);
$eventManager->addEventHandler('sale', 'OnSaleOrderSaved', ['Plateam\\Partner\\EventHandlers', 'onOrderSaved']);
$eventManager->addEventHandler('sale', 'OnSalePaymentEntitySaved', ['Plateam\\Partner\\EventHandlers', 'onPaymentSaved']);
$eventManager->addEventHandler('sale', 'OnSaleOrderPaid', ['Plateam\\Partner\\EventHandlers', 'onOrderPaid']);
$eventManager->addEventHandler('sale', 'OnSaleOrderCanceled', ['Plateam\\Partner\\EventHandlers', 'onOrderCanceled']);

\Plateam\Partner\PromoBridge::register();
