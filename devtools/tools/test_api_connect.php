<?php

/**
 * Internal connectivity check. Uses module Config (pla.team by default).
 * Not part of the marketplace package.
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!\Bitrix\Main\Loader::includeModule('plateam.partner')) {
    echo json_encode(['error' => 'module_not_loaded'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    die();
}

use Plateam\Partner\ApiClient;
use Plateam\Partner\Config;

$client = new ApiClient();
$result = $client->partnersMe();

echo json_encode([
    'url' => Config::apiBase() . '/partners/me',
    'platformOrigin' => Config::platformOrigin(),
    'siteOrigin' => Config::siteOrigin(),
    'hasKey' => Config::apiKey() !== '',
    'status' => $result['status'],
    'ok' => $result['ok'],
    'error' => $result['error'] ?? null,
    'body' => $result['body'],
    'bodyHead' => is_string($result['raw'] ?? null) ? substr($result['raw'], 0, 200) : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
