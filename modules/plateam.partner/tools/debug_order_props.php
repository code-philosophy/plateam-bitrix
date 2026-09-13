<?php

use Bitrix\Main\Loader;
use Plateam\Partner\SessionBridge;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('sale')) {
    http_response_code(503);
    echo json_encode(['error' => 'sale_not_loaded']);
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

$defs = [];
$rs = CSaleOrderProps::GetList(['SORT' => 'ASC'], ['CODE' => 'PLATEAM_%']);
while ($row = $rs->Fetch()) {
    $defs[] = [
        'id' => (int) $row['ID'],
        'code' => (string) $row['CODE'],
        'personTypeId' => (int) $row['PERSON_TYPE_ID'],
        'active' => (string) $row['ACTIVE'],
    ];
}

$onOrder = [];
$personTypeId = null;
if ($orderId > 0) {
    $order = \Bitrix\Sale\Order::load($orderId);
    if ($order) {
        $personTypeId = (int) $order->getPersonTypeId();
        foreach ($order->getPropertyCollection() as $prop) {
            $code = (string) $prop->getField('CODE');
            if (str_starts_with($code, 'PLATEAM_')) {
                $onOrder[] = [
                    'code' => $code,
                    'value' => (string) $prop->getValue(),
                    'orderPropsId' => (int) $prop->getPropertyId(),
                ];
            }
        }
    }
}

$stash = null;
if (isset($_SESSION[SessionBridge::SESSION_KEY])) {
    $stash = $_SESSION[SessionBridge::SESSION_KEY];
}

echo json_encode([
    'orderId' => $orderId,
    'personTypeId' => $personTypeId,
    'definitions' => $defs,
    'onOrder' => $onOrder,
    'readOrderProps' => $orderId > 0 && isset($order) ? SessionBridge::readOrderProps($order) : null,
    'sessionStash' => $stash,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
