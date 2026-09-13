<?php

/**
 * One-shot payload for post-payment finish modal (Bitrix).
 * GET /local/modules/plateam.partner/tools/pending_finish.php
 *   ?visitorId=…[&orderId=…]
 *
 * Returns { ok, orderId, issued: { sesKop, uesKop } } when paid sync stored PLATEAM_ISSUED.
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Plateam\Partner\OrderPropertyInstaller;
use Plateam\Partner\SessionBridge;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!Loader::includeModule('sale') || !Loader::includeModule('plateam.partner')) {
    echo json_encode(['ok' => false, 'error' => 'module'], JSON_UNESCAPED_UNICODE);
    die();
}

OrderPropertyInstaller::install();

$visitorId = trim((string) ($_GET['visitorId'] ?? ''));
$orderIdRaw = trim((string) ($_GET['orderId'] ?? $_GET['ORDER_ID'] ?? ''));
$orderId = ctype_digit($orderIdRaw) ? (int) $orderIdRaw : 0;

/**
 * @return array{ok:bool, orderId?:int, accountNumber?:string, issued?:array{sesKop:int,uesKop:int}, error?:string, pending?:bool}
 */
function plateam_finish_payload(\Bitrix\Sale\Order $order, string $visitorId): array
{
    if (!$order->isPaid()) {
        return ['ok' => false, 'pending' => true, 'error' => 'not_paid', 'orderId' => (int) $order->getId()];
    }

    $props = SessionBridge::readOrderProps($order);
    if ($visitorId !== '') {
        $orderVid = trim((string) ($props['visitorId'] ?? ''));
        if ($orderVid !== '' && strcasecmp($orderVid, $visitorId) !== 0) {
            return ['ok' => false, 'error' => 'visitor_mismatch', 'orderId' => (int) $order->getId()];
        }
    }

    if (($props['paidSent'] ?? '') !== 'Y') {
        return ['ok' => false, 'pending' => true, 'error' => 'paid_not_synced', 'orderId' => (int) $order->getId()];
    }

    $raw = trim((string) ($props['issued'] ?? ''));
    if ($raw === '') {
        return ['ok' => false, 'pending' => true, 'error' => 'issued_missing', 'orderId' => (int) $order->getId()];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'issued_invalid', 'orderId' => (int) $order->getId()];
    }

    return [
        'ok' => true,
        'orderId' => (int) $order->getId(),
        'accountNumber' => (string) $order->getField('ACCOUNT_NUMBER'),
        'issued' => [
            'sesKop' => (int) ($decoded['sesKop'] ?? 0),
            'uesKop' => (int) ($decoded['uesKop'] ?? 0),
        ],
    ];
}

if ($orderId > 0) {
    $order = \Bitrix\Sale\Order::load($orderId);
    if (!$order) {
        echo json_encode(['ok' => false, 'error' => 'order_not_found'], JSON_UNESCAPED_UNICODE);
        die();
    }
    echo json_encode(plateam_finish_payload($order, $visitorId), JSON_UNESCAPED_UNICODE);
    die();
}

// Fallback: latest paid order for this visitor (or session stash).
$sessionOrderId = 0;
if (isset($_SESSION['PLATEAM_AWAIT_FINISH_ORDER'])) {
    $sessionOrderId = (int) $_SESSION['PLATEAM_AWAIT_FINISH_ORDER'];
}

if ($sessionOrderId > 0) {
    $order = \Bitrix\Sale\Order::load($sessionOrderId);
    if ($order) {
        $payload = plateam_finish_payload($order, $visitorId);
        if (!empty($payload['ok'])) {
            unset($_SESSION['PLATEAM_AWAIT_FINISH_ORDER']);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        die();
    }
}

if ($visitorId === '') {
    echo json_encode(['ok' => false, 'pending' => true, 'error' => 'visitor_required'], JSON_UNESCAPED_UNICODE);
    die();
}

$rs = \Bitrix\Sale\Internals\OrderTable::getList([
    'order' => ['ID' => 'DESC'],
    'filter' => ['=PAYED' => 'Y'],
    'select' => ['ID'],
    'limit' => 15,
]);

while ($row = $rs->fetch()) {
    $candidate = \Bitrix\Sale\Order::load((int) $row['ID']);
    if (!$candidate) {
        continue;
    }
    $props = SessionBridge::readOrderProps($candidate);
    $orderVid = trim((string) ($props['visitorId'] ?? ''));
    if ($orderVid === '' || strcasecmp($orderVid, $visitorId) !== 0) {
        continue;
    }
    $payload = plateam_finish_payload($candidate, $visitorId);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    die();
}

echo json_encode(['ok' => false, 'pending' => true, 'error' => 'no_matching_order'], JSON_UNESCAPED_UNICODE);
