<?php

/**
 * Статус демо-промокодов для корзины (AJAX после применения купона).
 * GET/POST /local/modules/plateam.partner/tools/promo_status.php
 *
 * POST JSON { "networkActive": true } — зафиксировать, что виджет PLATEAM уже показан
 * (чужие промокоды с этого момента снимаются).
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Plateam\Partner\PromoBridge;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!Loader::includeModule('plateam.partner')) {
    echo json_encode(['ok' => false, 'error' => 'module'], JSON_UNESCAPED_UNICODE);
    die();
}

$raw = file_get_contents('php://input');
$input = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if ($input === [] && !empty($_POST) && is_array($_POST)) {
    $input = $_POST;
}

if (!empty($input['networkActive'])) {
    PromoBridge::markNetworkActive();
}

$stripped = PromoBridge::reconcileAppliedCoupons();
$state = PromoBridge::clientState();
$state['ok'] = true;
$state['stripped'] = $stripped;

echo json_encode($state, JSON_UNESCAPED_UNICODE);
