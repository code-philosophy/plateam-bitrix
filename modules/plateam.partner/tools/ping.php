<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

$loaded = \Bitrix\Main\Loader::includeModule('plateam.partner');
$registered = \Bitrix\Main\ModuleManager::isModuleInstalled('plateam.partner');

echo json_encode([
    'loaded' => (bool) $loaded,
    'registered' => (bool) $registered,
    'partner_code' => $loaded ? \Plateam\Partner\Config::partnerCode() : null,
    'api_configured' => $loaded ? \Plateam\Partner\Config::isConfigured() : false,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
