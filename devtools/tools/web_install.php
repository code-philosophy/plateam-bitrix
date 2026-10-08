<?php

/**
 * Internal team bootstrap only (east / lab).
 * Copy this file to the site if needed — it is NOT part of the marketplace module package.
 *
 * Defaults target production pla.team (partner onboarding contour).
 * Set PLATEAM_DEVTOOLS_DEMO=1 to seed internal *.demo.pla.team (team sandbox, not partner LK).
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Plateam\Partner\OptionsMigrator;
use Plateam\Partner\OrderPropertyInstaller;

header('Content-Type: text/plain; charset=utf-8');

$moduleId = 'plateam.partner';
$steps = [];
$useDemoHost = (string) (getenv('PLATEAM_DEVTOOLS_DEMO') ?: '') === '1';

if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/local/modules/plateam.partner/include.php')) {
    echo "Module files missing\n";
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/plateam.partner/include.php';

$paySystemInstaller = dirname(__DIR__) . '/PaySystemInstaller.php';
if (is_file($paySystemInstaller)) {
    require_once $paySystemInstaller;
}

if (!ModuleManager::isModuleInstalled($moduleId)) {
    ModuleManager::registerModule($moduleId);
    $steps[] = 'registered module';
} else {
    $steps[] = 'module already registered';
}

OptionsMigrator::migrate();
$steps[] = 'options migrator ran';

if (Loader::includeModule('sale')) {
    OrderPropertyInstaller::install();
    $steps[] = 'order properties ensured';
    if (class_exists(\Plateam\Partner\PaySystemInstaller::class)) {
        try {
            $steps = array_merge($steps, \Plateam\Partner\PaySystemInstaller::install());
        } catch (\Throwable $e) {
            $steps[] = 'pay system ERROR: ' . $e->getMessage();
        }
    }
}

if ($useDemoHost) {
    $defaults = [
        'partner_code' => 'north',
        'platform_origin' => 'https://app.demo.pla.team',
        'api_base' => 'https://app.demo.pla.team/api/v0',
        'go_origin' => 'https://go.demo.pla.team',
        'demo_ref_token' => 'demo-ref-north',
        'own_promo_code' => 'PLATEAM',
        'foreign_promo_code' => 'SHOP10',
        'widget_version' => '1.1.0',
    ];
    $steps[] = 'WARNING: internal demo host (not partner onboarding)';
} else {
    $defaults = [
        'partner_code' => 'north',
        'platform_origin' => 'https://pla.team',
        'api_base' => 'https://pla.team/api/v0',
        'go_origin' => 'https://go.pla.team',
        'demo_ref_token' => 'demo-ref-north',
        'own_promo_code' => 'PLATEAM',
        'foreign_promo_code' => 'SHOP10',
        'widget_version' => '1.1.0',
    ];
}

foreach ($defaults as $key => $value) {
    if (Option::get($moduleId, $key, '') === '' && $value !== '') {
        Option::set($moduleId, $key, $value);
    }
}

if ($useDemoHost && Option::get($moduleId, 'api_key', '') === '') {
    Option::set($moduleId, 'api_key', 'demo-north-key');
    $steps[] = 'seeded legacy demo key (team sandbox only)';
}

$steps[] = $useDemoHost ? 'options ok (internal demo contour)' : 'options ok (pla.team — set pk_test_/pk_live_ in admin)';

echo implode("\n", $steps) . "\n";
echo "Done. Remove or protect this script after use.\n";
