<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Plateam\Partner\PaySystemInstaller;

header('Content-Type: text/plain; charset=utf-8');

if (!Loader::includeModule('plateam.partner') || !Loader::includeModule('sale')) {
    echo "module not loaded\n";
    die();
}

try {
    echo implode("\n", PaySystemInstaller::install()) . "\nDone.\n";
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
