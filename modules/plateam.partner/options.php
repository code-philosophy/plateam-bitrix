<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Plateam\Partner\ApiClient;
use Plateam\Partner\Config;

$moduleId = 'plateam.partner';
Loc::loadMessages(__FILE__);

if ($REQUEST_METHOD === 'POST' && check_bitrix_sessid()) {
    if (isset($_POST['test_api'])) {
        $client = new ApiClient(
            $_POST['api_base'] ?: Config::apiBase(),
            $_POST['api_key'] ?: Config::apiKey(),
        );
        $result = $client->partnersMe();
        if ($result['ok']) {
            CAdminMessage::ShowMessage([
                'TYPE' => 'OK',
                'MESSAGE' => 'OK: ' . ($result['body']['code'] ?? 'partner'),
            ]);
        } else {
            CAdminMessage::ShowMessage([
                'TYPE' => 'ERROR',
                'MESSAGE' => 'HTTP ' . $result['status'] . ': ' . ($result['raw'] ?? 'error'),
            ]);
        }
    } else {
        Option::set($moduleId, 'partner_code', trim((string) $_POST['partner_code']));
        Option::set($moduleId, 'api_key', trim((string) $_POST['api_key']));
        Option::set($moduleId, 'platform_origin', rtrim(trim((string) $_POST['platform_origin']), '/'));
        Option::set($moduleId, 'api_base', rtrim(trim((string) $_POST['api_base']), '/'));
        Option::set($moduleId, 'widget_version', trim((string) $_POST['widget_version']));
        Option::set(
            $moduleId,
            'block_foreign_with_referral',
            !empty($_POST['block_foreign_with_referral']) ? 'Y' : 'N'
        );
        CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE') . ': saved']);
    }
}

$partnerCode = Option::get($moduleId, 'partner_code', '');
$apiKey = Option::get($moduleId, 'api_key', '');
$platformOrigin = Option::get($moduleId, 'platform_origin', 'https://pla.team');
$apiBase = Option::get($moduleId, 'api_base', 'https://pla.team/api/v0');
$widgetVersion = Option::get($moduleId, 'widget_version', '20260913h');
$blockForeign = Option::get($moduleId, 'block_foreign_with_referral', 'Y') !== 'N';

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'edit1', 'TAB' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE'), 'TITLE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE')],
]);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('PLATEAM_PARTNER_OPT_PARTNER_CODE') ?>:</td>
        <td><input type="text" name="partner_code" value="<?= htmlspecialcharsbx($partnerCode) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_API_KEY') ?>:</td>
        <td><input type="password" name="api_key" value="<?= htmlspecialcharsbx($apiKey) ?>" size="60" autocomplete="off"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_PLATFORM') ?>:</td>
        <td><input type="text" name="platform_origin" value="<?= htmlspecialcharsbx($platformOrigin) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_API_BASE') ?>:</td>
        <td><input type="text" name="api_base" value="<?= htmlspecialcharsbx($apiBase) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_WIDGET_VER') ?>:</td>
        <td><input type="text" name="widget_version" value="<?= htmlspecialcharsbx($widgetVersion) ?>" size="20"></td>
    </tr>
    <tr>
        <td valign="top"><?= Loc::getMessage('PLATEAM_PARTNER_OPT_BLOCK_FOREIGN') ?>:</td>
        <td>
            <input type="checkbox" name="block_foreign_with_referral" value="Y"<?= $blockForeign ? ' checked' : '' ?>>
            <br><small><?= Loc::getMessage('PLATEAM_PARTNER_OPT_BLOCK_FOREIGN_HINT') ?></small>
        </td>
    </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('PLATEAM_PARTNER_BTN_SAVE') ?>">
    <input type="submit" name="test_api" value="<?= Loc::getMessage('PLATEAM_PARTNER_BTN_TEST') ?>">
    <?php $tabControl->End(); ?>
</form>
