<?php

namespace Plateam\Partner;

use Bitrix\Main\Event;
use Bitrix\Main\Loader;

class EventHandlers
{
    private static bool $orderSaveBusy = false;
    private static bool $coverBusy = false;

    /** Stash → свойства и сумма платежа до сохранения заказа (SOA сохраняет несколько раз). */
    public static function onOrderBeforeSaved(Event $event): void
    {
        if (!Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }
        /** @var \Bitrix\Sale\Order|null $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof \Bitrix\Sale\Order || $order->isPaid()) {
            return;
        }

        $checkout = SessionBridge::peekCheckout();
        if (!$checkout) {
            return;
        }

        self::applyCheckoutToOrder($order, $checkout);
    }

    public static function onOrderSaved(Event $event): void
    {
        if (self::$orderSaveBusy || !Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }
        /** @var \Bitrix\Sale\Order|null $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof \Bitrix\Sale\Order) {
            return;
        }

        self::$orderSaveBusy = true;
        try {
            $isNew = (bool) $event->getParameter('IS_NEW');
            if ($isNew) {
                SessionBridge::clearCheckout();
                SessionBridge::flagResetPickCookie();
            }
            self::coverCertificateRemainder($order);
            self::maybeAutoPayZeroCash($order);
        } finally {
            self::$orderSaveBusy = false;
        }
    }

    public static function onPaymentSaved(Event $event): void
    {
        if (self::$coverBusy || !Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }
        $payment = $event->getParameter('ENTITY');
        if (!$payment instanceof \Bitrix\Sale\Payment || !$payment->isPaid() || $payment->isInner()) {
            return;
        }
        if ((string) $payment->getField('XML_ID') === OrderDiscount::CERT_PAYMENT_XML_ID) {
            return;
        }
        $collection = $payment->getCollection();
        if (!$collection) {
            return;
        }
        $order = $collection->getOrder();
        if ($order instanceof \Bitrix\Sale\Order) {
            self::coverCertificateRemainder($order);
        }
    }

    /** Полное покрытие сертификатами — без реквизитов, сразу paid в Bitrix. */
    private static function maybeAutoPayZeroCash(\Bitrix\Sale\Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        $props = SessionBridge::readOrderProps($order);
        $cashKop = isset($props['cashKop']) && $props['cashKop'] !== ''
            ? (int) $props['cashKop']
            : max(0, SessionBridge::rubToKop($order->getPrice()) - (int) ($props['sesKop'] ?? 0) - (int) ($props['uesKop'] ?? 0));
        if ($cashKop > 0) {
            return;
        }

        $certKop = (int) ($props['sesKop'] ?? 0) + (int) ($props['uesKop'] ?? 0);
        if ($certKop <= 0) {
            return;
        }

        $paymentCollection = $order->getPaymentCollection();
        foreach ($paymentCollection as $payment) {
            if ($payment->isInner()) {
                continue;
            }
            if (!$payment->isPaid()) {
                $payment->setPaid('Y');
            }
        }
        $order->save();
    }

    public static function onOrderPaid(Event $event): void
    {
        if (!Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }
        /** @var \Bitrix\Sale\Order|null $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof \Bitrix\Sale\Order || !$order->isPaid()) {
            return;
        }

        $props = SessionBridge::readOrderProps($order);
        if (($props['paidSent'] ?? '') === 'Y') {
            return;
        }

        self::scheduleSync((int) $order->getId());
    }

    public static function scheduleSync(int $orderId): void
    {
        register_shutdown_function(static function () use ($orderId): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            self::syncOrderPaid($orderId);
        });
    }

    /**
     * Карта (cash) уже оплачена, сертификаты ещё нет — закрываем остаток,
     * иначе заказ Bitrix остаётся неоплаченным и OnSaleOrderPaid не вызывается.
     */
    public static function coverCertificateRemainder(\Bitrix\Sale\Order $order): void
    {
        if (self::$coverBusy || $order->isPaid()) {
            return;
        }

        $props = SessionBridge::readOrderProps($order);
        $certKop = (int) ($props['sesKop'] ?? 0) + (int) ($props['uesKop'] ?? 0);
        if ($certKop <= 0) {
            return;
        }

        $collection = $order->getPaymentCollection();
        $cashPaid = false;
        $certPayment = null;
        $paidSum = 0.0;
        foreach ($collection as $payment) {
            if ($payment->isPaid()) {
                $paidSum += (float) $payment->getSum();
            }
            if ($payment->isInner()) {
                continue;
            }
            if ((string) $payment->getField('XML_ID') === OrderDiscount::CERT_PAYMENT_XML_ID) {
                $certPayment = $payment;
                continue;
            }
            if ($payment->isPaid()) {
                $cashPaid = true;
            }
        }
        if (!$cashPaid) {
            return;
        }

        $remain = round((float) $order->getPrice() - $paidSum, 2);
        if ($remain <= 0.009) {
            self::scheduleSync((int) $order->getId());
            return;
        }

        $paySystemId = OrderDiscount::defaultPaySystemId((int) $order->getPersonTypeId());
        if ($paySystemId <= 0) {
            return;
        }

        self::$coverBusy = true;
        try {
            if ($certPayment) {
                if (!$certPayment->isPaid()) {
                    $certPayment->setField('SUM', $remain);
                    $certPayment->setPaid('Y');
                }
            } else {
                $certPayment = $collection->createItem();
                $certPayment->setFields([
                    'SUM' => $remain,
                    'CURRENCY' => $order->getCurrency(),
                    'PAY_SYSTEM_ID' => $paySystemId,
                    'PAY_SYSTEM_NAME' => 'PLATEAM сертификаты',
                    'XML_ID' => OrderDiscount::CERT_PAYMENT_XML_ID,
                ]);
                $certPayment->setPaid('Y');
            }
            $order->save();
        } finally {
            self::$coverBusy = false;
        }
    }

    public static function onOrderCanceled(Event $event): void
    {
        if (!Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }
        /** @var \Bitrix\Sale\Order|null $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof \Bitrix\Sale\Order) {
            return;
        }

        $orderId = SessionBridge::orderIdForApi($order);
        (new OrderSync())->markCancelled(['orderId' => $orderId]);
    }

    public static function syncOrderPaid(int $orderId): void
    {
        if (self::$orderSaveBusy || !Loader::includeModule('sale') || !Config::isConfigured()) {
            return;
        }

        self::$orderSaveBusy = true;
        try {
            $order = \Bitrix\Sale\Order::load($orderId);
            if (!$order instanceof \Bitrix\Sale\Order || !$order->isPaid()) {
                return;
            }

            self::ensureCheckoutOnOrder($order);

            $props = SessionBridge::readOrderProps($order);
            if (($props['paidSent'] ?? '') === 'Y') {
                return;
            }

            $visitorId = trim((string) ($props['visitorId'] ?? ''));
            $userId = trim((string) ($props['userId'] ?? ''));
            if ($visitorId === '' && $userId === '') {
                SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['paidSent'], 'E:identity');
                $order->save();
                return;
            }

            self::ensureHold($order, $props);
            $props = SessionBridge::readOrderProps($order);

            $apiOrderId = SessionBridge::orderIdForApi($order);
            $sesKop = (int) ($props['sesKop'] ?? 0);
            $uesKop = (int) ($props['uesKop'] ?? 0);
            $certTotal = $sesKop + $uesKop;
            $orderTotalKop = SessionBridge::rubToKop($order->getPrice());
            $cashKop = max(0, $orderTotalKop - $certTotal);
            if (isset($props['cashKop']) && $props['cashKop'] !== '') {
                $cashKop = (int) $props['cashKop'];
            }

            $result = (new OrderSync())->markPaid([
                'orderId' => $apiOrderId,
                'visitorId' => $visitorId !== '' ? $visitorId : null,
                'userId' => $userId !== '' ? $userId : null,
                'cashKop' => $cashKop,
                'holdId' => trim((string) ($props['holdId'] ?? '')) ?: null,
            ]);

            if ($result['ok']) {
                // Ensure PLATEAM_ISSUED prop exists (idempotent) for finish modal.
                OrderPropertyInstaller::install();
                SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['paidSent'], 'Y');
                SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['cashKop'], (string) $cashKop);
                $issuedJson = self::extractIssuedJson($result);
                if ($issuedJson !== '') {
                    SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['issued'], $issuedJson);
                }
                $order->save();
                return;
            }

            $err = self::formatApiError($result);
            SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['paidSent'], 'E:' . substr($err, 0, 40));
            $order->save();
        } finally {
            self::$orderSaveBusy = false;
        }
    }

    /** @param array<string, mixed> $result */
    private static function formatApiError(array $result): string
    {
        if (!empty($result['error']) && is_string($result['error'])) {
            return $result['error'];
        }
        if (is_array($result['body'] ?? null) && !empty($result['body']['error'])) {
            return (string) $result['body']['error'];
        }
        $status = (int) ($result['status'] ?? 0);
        return $status > 0 ? 'http_' . $status : 'api_error';
    }

    /**
     * Persist issued SES/UES from orders/paid for the Bitrix finish modal.
     * @param array<string, mixed> $result
     */
    private static function extractIssuedJson(array $result): string
    {
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $issued = $body['issued'] ?? null;
        if (!is_array($issued)) {
            return '';
        }
        $payload = [
            'sesKop' => (int) ($issued['sesKop'] ?? 0),
            'uesKop' => (int) ($issued['uesKop'] ?? 0),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '';
    }

    private static function ensureCheckoutOnOrder(\Bitrix\Sale\Order $order): void
    {
        $checkout = SessionBridge::peekCheckout();
        $props = SessionBridge::readOrderProps($order);
        $needVisitor = trim((string) ($props['visitorId'] ?? '')) === '';
        $needCert = ((int) ($props['sesKop'] ?? 0) + (int) ($props['uesKop'] ?? 0)) <= 0
            && $checkout
            && ((int) ($checkout['sesKop'] ?? 0) + (int) ($checkout['uesKop'] ?? 0)) > 0;
        if (!$checkout || (!$needVisitor && !$needCert)) {
            return;
        }

        self::applyCheckoutToOrder($order, $checkout);
        $order->save();
    }

    /** @param array<string, string> $props */
    private static function ensureHold(\Bitrix\Sale\Order $order, array $props): void
    {
        $sesKop = (int) ($props['sesKop'] ?? 0);
        $uesKop = (int) ($props['uesKop'] ?? 0);
        $holdId = trim((string) ($props['holdId'] ?? ''));
        $visitorId = trim((string) ($props['visitorId'] ?? ''));
        if (($sesKop + $uesKop) <= 0 || $holdId !== '' || $visitorId === '') {
            return;
        }

        $orderId = SessionBridge::orderIdForApi($order);
        $hold = (new HoldService())->createHold([
            'orderId' => $orderId,
            'visitorId' => $visitorId,
            'userId' => trim((string) ($props['userId'] ?? '')) ?: null,
            'sesKop' => $sesKop,
            'uesKop' => $uesKop,
        ]);

        if ($hold['ok'] && is_array($hold['body']) && !empty($hold['body']['holdId'])) {
            SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['holdId'], (string) $hold['body']['holdId']);
            $order->save();
        }
    }

    private static function applyCheckoutToOrder(\Bitrix\Sale\Order $order, array $checkout): void
    {
        $visitorId = trim((string) ($checkout['visitorId'] ?? ''));
        $userId = trim((string) ($checkout['userId'] ?? ''));
        $orderTotalKop = SessionBridge::rubToKop($order->getPrice());
        $amounts = OrderDiscount::normalizeAmounts(
            $orderTotalKop,
            (int) ($checkout['sesKop'] ?? 0),
            (int) ($checkout['uesKop'] ?? 0),
        );

        if ($visitorId !== '') {
            SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['visitorId'], $visitorId);
        }
        if ($userId !== '') {
            SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['userId'], $userId);
        }
        SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['sesKop'], (string) $amounts['sesKop']);
        SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['uesKop'], (string) $amounts['uesKop']);
        SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['cashKop'], (string) $amounts['cashKop']);

        OrderDiscount::apply($order, $amounts['certKop'], $amounts['cashKop']);
    }
}
