<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

$url = 'https://app.demo.pla.team/api/v0/partners/me';
$key = \Bitrix\Main\Config\Option::get('plateam.partner', 'api_key', '');

$headers = [
    'Authorization: Bearer ' . $key,
    'Accept: application/json',
    'Origin: https://east.evosreda.ru',
];

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => 8,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$err = null;
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    $err = error_get_last()['message'] ?? 'file_get_contents failed';
}

$status = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $status = (int) $m[1];
}

echo json_encode([
    'url' => $url,
    'hasKey' => $key !== '',
    'status' => $status,
    'error' => $err,
    'bodyHead' => $raw === false ? null : substr($raw, 0, 200),
    'allowUrlFopen' => ini_get('allow_url_fopen'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
