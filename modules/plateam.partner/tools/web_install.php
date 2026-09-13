<?php

/**
 * Staging / pilot bootstrap only — seeds demo.pla.team + north.
 * Production partners: install via admin UI and set options to https://pla.team.
 * Open once: /local/modules/plateam.partner/tools/web_install.php
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Plateam\Partner\OrderPropertyInstaller;
use Plateam\Partner\PaySystemInstaller;

header('Content-Type: text/plain; charset=utf-8');

$moduleId = 'plateam.partner';
$steps = [];

if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/local/modules/plateam.partner/include.php')) {
    echo "Module files missing\n";
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/plateam.partner/include.php';

if (!ModuleManager::isModuleInstalled($moduleId)) {
    ModuleManager::registerModule($moduleId);
    $steps[] = 'registered module';
} else {
    $steps[] = 'module already registered';
}

if (Loader::includeModule('sale')) {
    OrderPropertyInstaller::install();
    $steps[] = 'order properties ensured';
    try {
        $steps = array_merge($steps, PaySystemInstaller::install());
    } catch (\Throwable $e) {
        $steps[] = 'pay system ERROR: ' . $e->getMessage();
    }
}

$defaults = [
    'partner_code' => 'north',
    'platform_origin' => 'https://app.demo.pla.team',
    'api_base' => 'https://app.demo.pla.team/api/v0',
    'widget_version' => '20260818',
    'api_key' => '',
];
foreach ($defaults as $key => $value) {
    if (Option::get($moduleId, $key, '') === '' && $value !== '') {
        Option::set($moduleId, $key, $value);
    }
}

// Staging demo key for partner north (override in module settings UI).
if (Option::get($moduleId, 'api_key', '') === '') {
    Option::set($moduleId, 'api_key', 'demo-north-key');
}

$steps[] = 'options ok';

echo implode("\n", $steps) . "\n";
echo "Done. Remove or protect this script after use.\n";
