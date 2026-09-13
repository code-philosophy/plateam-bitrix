<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('plateam.partner')) {
    header('Content-Type: text/plain');
    echo 'module not loaded';
    die();
}

$APPLICATION->IncludeComponent('plateam:widget', '', [], false);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
