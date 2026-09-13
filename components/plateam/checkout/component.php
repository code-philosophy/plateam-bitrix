<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Plateam\Partner\Config;
use Plateam\Partner\BasketTotal;
use Plateam\Partner\PromoBridge;

if (!Loader::includeModule('plateam.partner')) {
    return;
}

$orderTotalKop = 0;
if (isset($arParams['ORDER_TOTAL_KOP']) && (int) $arParams['ORDER_TOTAL_KOP'] > 0) {
    $orderTotalKop = (int) $arParams['ORDER_TOTAL_KOP'];
} else {
    $orderTotalKop = BasketTotal::currentKop();
}

$mode = isset($arParams['MODE']) ? strtolower(trim((string) $arParams['MODE'])) : 'cart';
if (!in_array($mode, ['cart', 'summary'], true)) {
    $mode = 'cart';
}

PromoBridge::reconcileAppliedCoupons();
$promo = PromoBridge::clientState();

$arResult['STASH_URL'] = '/local/modules/plateam.partner/tools/stash_checkout.php';
$arResult['PLATFORM_ORIGIN'] = Config::platformOrigin();
$arResult['ORDER_TOTAL_KOP'] = $orderTotalKop;
$arResult['MODE'] = $mode;
$arResult['PROMO_OWN_APPLIED'] = !empty($promo['ownApplied']);
$needsActivation = !empty($promo['needsActivation']);
if ($needsActivation) {
    PromoBridge::consumeActivationFlag();
}
$arResult['PROMO_NEEDS_ACTIVATION'] = $needsActivation;
$arResult['PROMO_ACTIVATE_REF'] = (string) ($promo['activateRef'] ?? Config::demoRefToken());
$arResult['PROMO_OWN_CODE'] = (string) ($promo['ownCode'] ?? Config::ownPromoCode());
$arResult['PROMO_FOREIGN_CODE'] = (string) ($promo['foreignCode'] ?? Config::foreignPromoCode());

$this->includeComponentTemplate();
