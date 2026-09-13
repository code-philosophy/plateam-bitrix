<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale\PaySystem\Manager;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('sale')) {
    http_response_code(503);
    echo json_encode(['error' => 'sale_not_loaded']);
    die();
}

$paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 10;
$paySystemId = 10;

try {
    [$orderId, $resolvedPaymentId] = Manager::getIdsByPayment($paymentId, 'ORDER');
    if (!$orderId || !$resolvedPaymentId) {
        echo json_encode(['error' => 'payment_not_found', 'paymentId' => $paymentId]);
        die();
    }

    $order = \Bitrix\Sale\Order::load($orderId);
    $payment = $order->getPaymentCollection()->getItemById($resolvedPaymentId);
    if (!$payment) {
        echo json_encode(['error' => 'payment_item_missing']);
        die();
    }

    $service = Manager::getObjectById($paySystemId);
    if (!$service) {
        echo json_encode(['error' => 'pay_system_not_found']);
        die();
    }

    $server = Context::getCurrent()->getServer();
    $post = [
        'BX_HANDLER' => 'PLATEAM_STUB',
        'PAYMENT_ID' => (string) $resolvedPaymentId,
        'PAYSYSTEM_ID' => (string) $paySystemId,
        'confirm' => 'Y',
    ];
    $request = new \Bitrix\Main\HttpRequest($server, $post, [], [], []);

    $processResult = $service->processRequest($request);
    $order = \Bitrix\Sale\Order::load($orderId);

    echo json_encode([
        'success' => $processResult->isSuccess(),
        'errors' => $processResult->getErrorMessages(),
        'orderPaid' => $order ? $order->isPaid() : null,
        'paymentPaid' => $payment->isPaid(),
        'orderId' => $orderId,
        'paymentId' => $resolvedPaymentId,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
