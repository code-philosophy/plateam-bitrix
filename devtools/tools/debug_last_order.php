<?php

use Bitrix\Main\Loader;
use Plateam\Partner\SessionBridge;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('sale') || !Loader::includeModule('plateam.partner')) {
    http_response_code(503);
    echo json_encode(['error' => 'modules_not_loaded']);
    die();
}

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if ($orderId <= 0) {
    $row = \Bitrix\Sale\Internals\OrderTable::getList([
        'order' => ['ID' => 'DESC'],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();
    $orderId = $row ? (int) $row['ID'] : 0;
}

if ($orderId <= 0) {
    echo json_encode(['error' => 'no_orders']);
    die();
}

$order = \Bitrix\Sale\Order::load($orderId);
if (!$order) {
    echo json_encode(['error' => 'order_not_found', 'orderId' => $orderId]);
    die();
}

$payments = [];
foreach ($order->getPaymentCollection() as $payment) {
    $payments[] = [
        'id' => (int) $payment->getId(),
        'account' => (string) $payment->getField('ACCOUNT_NUMBER'),
        'sum' => (float) $payment->getSum(),
        'paid' => $payment->isPaid() ? 'Y' : 'N',
        'paySystemId' => (int) $payment->getPaymentSystemId(),
        'paySystemName' => (string) $payment->getField('PAY_SYSTEM_NAME'),
    ];
}

echo json_encode([
    'orderId' => $orderId,
    'accountNumber' => (string) $order->getField('ACCOUNT_NUMBER'),
    'price' => (float) $order->getPrice(),
    'paid' => $order->isPaid() ? 'Y' : 'N',
    'canceled' => $order->isCanceled() ? 'Y' : 'N',
    'payments' => $payments,
    'plateam' => SessionBridge::readOrderProps($order),
    'apiConfigured' => \Plateam\Partner\Config::isConfigured(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
