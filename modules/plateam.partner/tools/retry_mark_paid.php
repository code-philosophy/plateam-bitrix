<?php

use Bitrix\Main\Loader;
use Plateam\Partner\EventHandlers;
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

$orderId = isset($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(['error' => 'order_id_required']);
    die();
}

$order = \Bitrix\Sale\Order::load($orderId);
if (!$order) {
    echo json_encode(['error' => 'order_not_found', 'orderId' => $orderId]);
    die();
}

if (!empty($_REQUEST['visitor_id'])) {
    SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['visitorId'], (string) $_REQUEST['visitor_id']);
}
if (isset($_REQUEST['ses_kop'])) {
    SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['sesKop'], (string) (int) $_REQUEST['ses_kop']);
}
if (isset($_REQUEST['ues_kop'])) {
    SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['uesKop'], (string) (int) $_REQUEST['ues_kop']);
}
if (isset($_REQUEST['cash_kop'])) {
    SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['cashKop'], (string) (int) $_REQUEST['cash_kop']);
}

EventHandlers::coverCertificateRemainder($order);
$order = \Bitrix\Sale\Order::load($orderId);

SessionBridge::writeOrderProp($order, SessionBridge::PROP_CODES['paidSent'], '');
$order->save();

EventHandlers::syncOrderPaid($orderId);
$order = \Bitrix\Sale\Order::load($orderId);

echo json_encode([
    'orderId' => $orderId,
    'paid' => $order && $order->isPaid() ? 'Y' : 'N',
    'plateam' => $order ? SessionBridge::readOrderProps($order) : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
