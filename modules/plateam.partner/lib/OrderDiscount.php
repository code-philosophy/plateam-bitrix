<?php

namespace Plateam\Partner;

class OrderDiscount
{
    public const CERT_PAYMENT_XML_ID = 'PLATEAM_CERT';
    /**
     * Корректирует сумму платежа под cashKop.
     * Не трогаем DISCOUNT_PRICE / doFinalAction — ломает AJAX-расчёт checkout Bitrix.
     */
    public static function apply(\Bitrix\Sale\Order $order, int $certKop, int $cashKop): void
    {
        self::adjustPayments($order, $cashKop);
    }

    /** @return array{sesKop:int,uesKop:int,certKop:int,cashKop:int} */
    public static function normalizeAmounts(int $orderTotalKop, int $sesKop, int $uesKop): array
    {
        $orderTotalKop = max(0, $orderTotalKop);
        $sesKop = max(0, min($sesKop, $orderTotalKop));
        $left = max(0, $orderTotalKop - $sesKop);
        $uesKop = max(0, min($uesKop, $left));
        $certKop = $sesKop + $uesKop;
        $cashKop = max(0, $orderTotalKop - $certKop);

        return [
            'sesKop' => $sesKop,
            'uesKop' => $uesKop,
            'certKop' => $certKop,
            'cashKop' => $cashKop,
        ];
    }

    private static function adjustPayments(\Bitrix\Sale\Order $order, int $cashKop): void
    {
        $cashRub = round(max(0, $cashKop) / 100, 2);
        $collection = $order->getPaymentCollection();

        foreach ($collection as $payment) {
            if ($payment->isInner()) {
                continue;
            }
            $payment->setField('SUM', $cashRub);
            return;
        }

        if ($cashRub <= 0) {
            return;
        }

        $paySystemId = self::defaultPaySystemId((int) $order->getPersonTypeId());
        if ($paySystemId <= 0) {
            return;
        }

        $payment = $collection->createItem();
        $payment->setFields([
            'SUM' => $cashRub,
            'CURRENCY' => $order->getCurrency(),
            'PAY_SYSTEM_ID' => $paySystemId,
            'PAY_SYSTEM_NAME' => PaySystemInstaller::PAY_SYSTEM_NAME,
        ]);
    }

    public static function defaultPaySystemId(int $personTypeId): int
    {
        if (!class_exists(\Bitrix\Sale\PaySystem\Manager::class)) {
            return 0;
        }
        $filter = ['ACTIVE' => 'Y', 'ACTION_FILE' => PaySystemInstaller::HANDLER_CODE];
        if ($personTypeId > 0) {
            $filter['PERSON_TYPE_ID'] = $personTypeId;
        }
        $row = \Bitrix\Sale\PaySystem\Manager::getList([
            'filter' => $filter,
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();
        if ($row) {
            return (int) $row['ID'];
        }
        $row = \Bitrix\Sale\PaySystem\Manager::getList([
            'filter' => ['ACTIVE' => 'Y', 'ACTION_FILE' => PaySystemInstaller::HANDLER_CODE],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();
        return $row ? (int) $row['ID'] : 0;
    }
}
