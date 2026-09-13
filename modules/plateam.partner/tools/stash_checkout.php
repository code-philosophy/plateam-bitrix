<?php

use Bitrix\Main\Loader;
use Plateam\Partner\SessionBridge;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    die();
}

if (!Loader::includeModule('plateam.partner')) {
    http_response_code(503);
    echo json_encode(['error' => 'module_not_loaded']);
    die();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    die();
}

SessionBridge::stashCheckout([
    'visitorId' => $data['visitorId'] ?? '',
    'userId' => $data['userId'] ?? '',
    'sesKop' => (int) ($data['sesKop'] ?? 0),
    'uesKop' => (int) ($data['uesKop'] ?? 0),
    'useSes' => !empty($data['useSes']),
    'useUes' => !empty($data['useUes']),
]);

echo json_encode(['ok' => true]);
