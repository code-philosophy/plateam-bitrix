<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

header('Content-Type: text/plain; charset=utf-8');

if (!Loader::includeModule('sale')) {
    echo "sale not loaded\n";
    die();
}

$siteId = 's1';
echo "SITE_ID=" . (defined('SITE_ID') ? SITE_ID : 'undef') . "\n\n";

echo "=== Person types ===\n";
$dbPt = \CSalePersonType::GetList(['SORT' => 'ASC'], ['LID' => $siteId]);
while ($pt = $dbPt->Fetch()) {
    echo "id={$pt['ID']} active={$pt['ACTIVE']} name={$pt['NAME']}\n";
}

echo "\n=== Pay systems (CSalePaySystem) ===\n";
$db = \CSalePaySystem::GetList(['SORT' => 'ASC'], []);
while ($row = $db->Fetch()) {
    if ((int) $row['ID'] >= 10) {
        echo "FULL id={$row['ID']}: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "id={$row['ID']} active={$row['ACTIVE']} pt=" . ($row['PERSON_TYPE_ID'] ?? '') . " action=" . ($row['ACTION_FILE'] ?? '') . " name={$row['NAME']}\n";
    }
}

echo "\n=== Handler load test ===\n";
$handlerPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/sale_payment/plateam_stub/handler.php';
echo "handler exists: " . (is_file($handlerPath) ? 'yes' : 'no') . "\n";
if (is_file($handlerPath)) {
    try {
        require_once $handlerPath;
        echo "class exists: " . (class_exists('Sale\\Handlers\\PaySystem\\Plateam_stubHandler') ? 'yes' : 'no') . "\n";
    } catch (\Throwable $e) {
        echo "handler load ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Pay systems id 5 vs 10 (Manager) ===\n";
foreach ([5, 10, 11] as $pid) {
    $row = \Bitrix\Sale\PaySystem\Manager::getById($pid);
    if ($row) {
        echo "id=$pid: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== Restrictions for 10 ===\n";
if (class_exists(\Bitrix\Sale\Services\PaySystem\Restrictions\Manager::class)) {
    $res = \Bitrix\Sale\Services\PaySystem\Restrictions\Manager::getList([
        'filter' => ['SERVICE_ID' => 10],
    ]);
    while ($r = $res->fetch()) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\nDone.\n";
