<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
/** @var CMain $APPLICATION */

$orderTotalKop = (int) ($arResult['ORDER_TOTAL_KOP'] ?? 0);
if ($orderTotalKop <= 0 && isset($arParams['ORDER_TOTAL_KOP'])) {
    $orderTotalKop = (int) $arParams['ORDER_TOTAL_KOP'];
}
$mode = (string) ($arResult['MODE'] ?? 'cart');
if (!in_array($mode, ['cart', 'summary'], true)) {
    $mode = 'cart';
}

$promoOwn = !empty($arResult['PROMO_OWN_APPLIED']);
$promoNeeds = !empty($arResult['PROMO_NEEDS_ACTIVATION']);
$activateRef = (string) ($arResult['PROMO_ACTIVATE_REF'] ?? '');
$ownCode = (string) ($arResult['PROMO_OWN_CODE'] ?? '');

$this->addExternalCss($templateFolder . '/style.css');
$scriptUrl = $templateFolder . '/script.js?v=20260913e';
$platformOrigin = rtrim((string) ($arResult['PLATFORM_ORIGIN'] ?? ''), '/');
$brandSrc = $platformOrigin !== ''
    ? $platformOrigin . '/brand/plateam-wordmark-light.svg?v=2'
    : '';
?>
<div
  id="plateam-checkout-root"
  class="plateam-checkout-host"
  data-stash-url="<?= htmlspecialcharsbx($arResult['STASH_URL']) ?>"
  data-platform-origin="<?= htmlspecialcharsbx($platformOrigin) ?>"
  data-order-total-kop="<?= (int) $orderTotalKop ?>"
  data-mode="<?= htmlspecialcharsbx($mode) ?>"
  data-promo-own="<?= $promoOwn ? '1' : '0' ?>"
  data-promo-needs-activation="<?= $promoNeeds ? '1' : '0' ?>"
  data-promo-activate-ref="<?= htmlspecialcharsbx($activateRef) ?>"
  data-promo-own-code="<?= htmlspecialcharsbx($ownCode) ?>"
  data-promo-foreign-code="<?= htmlspecialcharsbx((string) ($arResult['PROMO_FOREIGN_CODE'] ?? '')) ?>"
  data-promo-status-url="/local/modules/plateam.partner/tools/promo_status.php"
>
  <div class="plateam-checkout plateam-checkout-loading">
    <h4 class="plateam-checkout-brand"><?php if ($brandSrc !== '') { ?><img src="<?= htmlspecialcharsbx($brandSrc) ?>" alt="PLATEAM" width="140" height="22" decoding="async" /><?php } else { ?>PLATEAM<?php } ?></h4>
    <p class="muted">Подключаем баланс…</p>
  </div>
</div>
<script src="<?= htmlspecialcharsbx($scriptUrl) ?>"></script>
