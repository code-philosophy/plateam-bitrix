<?php

namespace Plateam\Partner;

class OrderDiscount
{
    public const CERT_PAYMENT_XML_ID = 'PLATEAM_CERT';

    /** Preferred stub handler from devtools (optional on partner shops). */
    public const PREFERRED_HANDLER = 'plateam_stub';

    public const FALLBACK_PAY_SYSTEM_NAME = 'PLATEAM';

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

        $paySystem = self::resolvePaySystem((int) $order->getPersonTypeId());
        if ($paySystem['id'] <= 0) {
            return;
        }

        $payment = $collection->createItem();
        $payment->setFields([
            'SUM' => $cashRub,
            'CURRENCY' => $order->getCurrency(),
            'PAY_SYSTEM_ID' => $paySystem['id'],
            'PAY_SYSTEM_NAME' => $paySystem['name'],
        ]);
    }

    public static function defaultPaySystemId(int $personTypeId): int
    {
        return self::resolvePaySystem($personTypeId)['id'];
    }

    /**
     * Prefer optional plateam_stub (devtools), otherwise any active pay system.
     * Does not depend on PaySystemInstaller (devtools-only).
     *
     * @return array{id:int,name:string}
     */
    private static function resolvePaySystem(int $personTypeId): array
    {
        $empty = ['id' => 0, 'name' => self::FALLBACK_PAY_SYSTEM_NAME];
        if (!class_exists(\Bitrix\Sale\PaySystem\Manager::class)) {
            return $empty;
        }

        $handler = self::PREFERRED_HANDLER;
        if (class_exists(PaySystemInstaller::class)) {
            $handler = PaySystemInstaller::HANDLER_CODE;
        }

        $row = self::fetchPaySystemRow([
            'ACTIVE' => 'Y',
            'ACTION_FILE' => $handler,
        ], $personTypeId);
        if (!$row) {
            $row = self::fetchPaySystemRow([
                'ACTIVE' => 'Y',
                'ACTION_FILE' => $handler,
            ], 0);
        }
        if (!$row) {
            $row = self::fetchPaySystemRow(['ACTIVE' => 'Y'], $personTypeId);
        }
        if (!$row) {
            $row = self::fetchPaySystemRow(['ACTIVE' => 'Y'], 0);
        }
        if (!$row) {
            return $empty;
        }

        $name = trim((string) ($row['NAME'] ?? ''));
        if ($name === '' && class_exists(PaySystemInstaller::class)) {
            $name = PaySystemInstaller::PAY_SYSTEM_NAME;
        }
        if ($name === '') {
            $name = self::FALLBACK_PAY_SYSTEM_NAME;
        }

        return [
            'id' => (int) $row['ID'],
            'name' => $name,
        ];
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>|null
     */
    private static function fetchPaySystemRow(array $filter, int $personTypeId): ?array
    {
        if ($personTypeId > 0) {
            $filter['PERSON_TYPE_ID'] = $personTypeId;
        }
        $row = \Bitrix\Sale\PaySystem\Manager::getList([
            'filter' => $filter,
            'select' => ['ID', 'NAME'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }
}
