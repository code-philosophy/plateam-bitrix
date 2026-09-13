<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $params */

$sum = (float) ($params['SUM'] ?? 0);
$orderAccount = htmlspecialcharsbx((string) ($params['ORDER_ACCOUNT'] ?? ''));
$paymentId = (int) ($params['PAYMENT_ID'] ?? 0);
$paymentAccount = htmlspecialcharsbx((string) ($params['PAYMENT_ACCOUNT_NUMBER'] ?? ''));
$paySystemId = (int) ($params['PAYSYSTEM_ID'] ?? 0);
$formAction = htmlspecialcharsbx((string) ($params['FORM_ACTION'] ?? '/bitrix/tools/sale_ps_result.php'));
$handlerTag = htmlspecialcharsbx((string) ($params['BX_HANDLER'] ?? 'PLATEAM_STUB'));
$formatted = number_format($sum, 0, '.', ' ') . ' ₽';
?>
<div class="plateam-stub-pay" style="max-width:480px;margin:1.5rem auto;padding:1.25rem;border:1px solid #ddd;border-radius:8px;background:#fafafa;font-family:sans-serif">
  <h3 style="margin:0 0 0.75rem;font-size:1.1rem">Тестовая оплата PLATEAM</h3>
  <p style="margin:0 0 0.5rem;color:#555;font-size:0.9rem">
    Имитация оплаты через банк на staging. Реквизиты карты не нужны — деньги никуда не списываются.
  </p>
  <?php if ($orderAccount !== ''): ?>
    <p style="margin:0 0 0.5rem"><strong>Заказ:</strong> <?= $orderAccount ?></p>
  <?php endif; ?>
  <p style="margin:0 0 1rem;font-size:1.15rem"><strong>К оплате:</strong> <?= $formatted ?></p>
  <form method="post" action="<?= $formAction ?>" target="_top">
    <input type="hidden" name="BX_HANDLER" value="<?= $handlerTag ?>">
    <?php if ($paymentId > 0): ?>
      <input type="hidden" name="PAYMENT_ID" value="<?= $paymentId ?>">
    <?php elseif ($paymentAccount !== ''): ?>
      <input type="hidden" name="PAYMENT_ACCOUNT_NUMBER" value="<?= $paymentAccount ?>">
    <?php endif; ?>
    <?php if ($paySystemId > 0): ?>
      <input type="hidden" name="PAYSYSTEM_ID" value="<?= $paySystemId ?>">
    <?php endif; ?>
    <input type="hidden" name="confirm" value="Y">
    <?= bitrix_sessid_post() ?>
    <button type="submit" style="padding:0.65rem 1.25rem;font-size:1rem;border:0;border-radius:6px;background:#2d6a4f;color:#fff;cursor:pointer">
      Оплатить (тест)
    </button>
  </form>
</div>
