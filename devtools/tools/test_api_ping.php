<?php

use Bitrix\Main\Loader;
use Plateam\Partner\ApiClient;
use Plateam\Partner\Config;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('plateam.partner')) {
    http_response_code(503);
    echo json_encode(['error' => 'module_not_loaded']);
    die();
}

$me = (new ApiClient())->partnersMe();
echo json_encode([
    'siteOrigin' => Config::siteOrigin(),
    'apiBase' => Config::apiBase(),
    'partnersMe' => $me,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
