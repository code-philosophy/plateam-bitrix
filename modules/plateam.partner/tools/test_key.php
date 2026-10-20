<?php

/**
 * Admin AJAX: GET /partners/me with form or saved credentials.
 * Used by module settings "Test key" button (avoids settings.php output buffering).
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('BX_SECURITY_SHOW_MESSAGE', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Plateam\Partner\ApiClient;
use Plateam\Partner\Config;
use Plateam\Partner\ToolsAuth;

header('Content-Type: application/json; charset=utf-8');

ToolsAuth::requireAdmin();
ToolsAuth::requireSessid();

if (!Loader::includeModule('plateam.partner')) {
    echo json_encode(['ok' => false, 'error' => 'module_not_loaded'], JSON_UNESCAPED_UNICODE);
    die();
}

$apiBase = trim((string) ($_REQUEST['api_base'] ?? ''));
$apiKey = trim((string) ($_REQUEST['api_key'] ?? ''));
if ($apiBase === '') {
    $apiBase = Config::apiBase();
}
if ($apiKey === '') {
    $apiKey = Config::apiKey();
}

if ($apiKey === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'missing_api_key',
        'message' => 'API key is empty (form and saved options)',
    ], JSON_UNESCAPED_UNICODE);
    die();
}

$client = new ApiClient($apiBase, $apiKey);
$result = $client->partnersMe();

if (!empty($result['ok'])) {
    $body = is_array($result['body'] ?? null) ? $result['body'] : [];
    $code = (string) ($body['code'] ?? 'partner');
    $keyEnv = trim((string) ($body['keyEnv'] ?? ''));
    $msg = 'OK: ' . $code;
    if ($keyEnv !== '') {
        $msg .= ' (keyEnv=' . $keyEnv . ')';
    }
    echo json_encode([
        'ok' => true,
        'message' => $msg,
        'code' => $code,
        'keyEnv' => $keyEnv,
        'body' => $body,
    ], JSON_UNESCAPED_UNICODE);
    die();
}

$detail = '';
if (!empty($result['error']) && is_string($result['error'])) {
    $detail = $result['error'];
} elseif (is_string($result['raw'] ?? null) && $result['raw'] !== '') {
    $detail = $result['raw'];
} elseif (is_array($result['body'] ?? null)) {
    $detail = json_encode($result['body'], JSON_UNESCAPED_UNICODE);
}
if ($detail === '' || $detail === false) {
    $detail = 'error';
}
if (strlen($detail) > 800) {
    $detail = substr($detail, 0, 800) . '…';
}

echo json_encode([
    'ok' => false,
    'status' => (int) ($result['status'] ?? 0),
    'error' => $detail,
    'message' => 'HTTP ' . (int) ($result['status'] ?? 0) . ': ' . $detail,
], JSON_UNESCAPED_UNICODE);
die();
