<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Plateam\Partner\ApiClient;
use Plateam\Partner\Config;

$moduleId = 'plateam.partner';
Loc::loadMessages(__FILE__);

if (!Loader::includeModule($moduleId)) {
    ShowError('Module plateam.partner is not installed');
    return;
}

/** @var array{TYPE:string,MESSAGE:string}|null $plateamAdminMessage */
$plateamAdminMessage = null;

$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
    || (isset($REQUEST_METHOD) && $REQUEST_METHOD === 'POST');

if ($isPost && check_bitrix_sessid()) {
    if (isset($_POST['test_api'])) {
        $apiBase = trim((string) ($_POST['api_base'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $client = new ApiClient(
            $apiBase !== '' ? $apiBase : Config::apiBase(),
            $apiKey !== '' ? $apiKey : Config::apiKey()
        );
        $result = $client->partnersMe();
        if (!empty($result['ok'])) {
            $body = is_array($result['body'] ?? null) ? $result['body'] : [];
            $code = (string) ($body['code'] ?? 'partner');
            $keyEnv = trim((string) ($body['keyEnv'] ?? ''));
            $msg = 'OK: ' . $code;
            if ($keyEnv !== '') {
                $msg .= ' (keyEnv=' . $keyEnv . ')';
            }
            $plateamAdminMessage = ['TYPE' => 'OK', 'MESSAGE' => $msg];
        } else {
            $detail = '';
            if (!empty($result['error']) && is_string($result['error'])) {
                $detail = $result['error'];
            } elseif (is_string($result['raw'] ?? null) && $result['raw'] !== '') {
                $detail = $result['raw'];
            } elseif (is_array($result['body'] ?? null)) {
                $detail = json_encode($result['body'], JSON_UNESCAPED_UNICODE);
            }
            if ($detail === '' || $detail === false) {
                $detail = 'error';
            }
            if (strlen($detail) > 500) {
                $detail = substr($detail, 0, 500) . '…';
            }
            $plateamAdminMessage = [
                'TYPE' => 'ERROR',
                'MESSAGE' => 'HTTP ' . (int) ($result['status'] ?? 0) . ': ' . $detail,
            ];
        }
    } elseif (isset($_POST['save']) || isset($_POST['Apply']) || isset($_POST['apply'])) {
        Option::set($moduleId, 'partner_code', trim((string) ($_POST['partner_code'] ?? '')));
        $postedKey = trim((string) ($_POST['api_key'] ?? ''));
        if ($postedKey !== '') {
            Option::set($moduleId, 'api_key', $postedKey);
        }
        Option::set($moduleId, 'platform_origin', rtrim(trim((string) ($_POST['platform_origin'] ?? '')), '/'));
        Option::set($moduleId, 'api_base', rtrim(trim((string) ($_POST['api_base'] ?? '')), '/'));
        Option::set($moduleId, 'go_origin', rtrim(trim((string) ($_POST['go_origin'] ?? '')), '/'));
        Option::set($moduleId, 'widget_version', trim((string) ($_POST['widget_version'] ?? '')));
        Option::set($moduleId, 'demo_ref_token', trim((string) ($_POST['demo_ref_token'] ?? '')));
        Option::set($moduleId, 'own_promo_code', strtoupper(trim((string) ($_POST['own_promo_code'] ?? ''))));
        Option::set($moduleId, 'foreign_promo_code', strtoupper(trim((string) ($_POST['foreign_promo_code'] ?? ''))));
        Option::set(
            $moduleId,
            'block_foreign_with_referral',
            !empty($_POST['block_foreign_with_referral']) ? 'Y' : 'N'
        );
        $plateamAdminMessage = [
            'TYPE' => 'OK',
            'MESSAGE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE') . ': saved',
        ];
    }
}

$partnerCode = Option::get($moduleId, 'partner_code', '');
$apiKey = Option::get($moduleId, 'api_key', '');
$platformOrigin = Option::get($moduleId, 'platform_origin', 'https://pla.team');
$apiBase = Option::get($moduleId, 'api_base', 'https://pla.team/api/v0');
$goOrigin = Option::get($moduleId, 'go_origin', 'https://go.pla.team');
$widgetVersion = Option::get($moduleId, 'widget_version', '1.0.0');
$refToken = Option::get($moduleId, 'demo_ref_token', '');
$ownPromo = Option::get($moduleId, 'own_promo_code', '');
$foreignPromo = Option::get($moduleId, 'foreign_promo_code', '');
$blockForeign = Option::get($moduleId, 'block_foreign_with_referral', 'Y') !== 'N';

if (is_array($plateamAdminMessage)) {
    CAdminMessage::ShowMessage($plateamAdminMessage);
}

$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE'),
        'TITLE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE'),
    ],
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
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_GO_ORIGIN') ?>:</td>
        <td><input type="text" name="go_origin" value="<?= htmlspecialcharsbx($goOrigin) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_REF_TOKEN') ?>:</td>
        <td><input type="text" name="demo_ref_token" value="<?= htmlspecialcharsbx($refToken) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_OWN_PROMO') ?>:</td>
        <td><input type="text" name="own_promo_code" value="<?= htmlspecialcharsbx($ownPromo) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_FOREIGN_PROMO') ?>:</td>
        <td><input type="text" name="foreign_promo_code" value="<?= htmlspecialcharsbx($foreignPromo) ?>" size="40"></td>
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
