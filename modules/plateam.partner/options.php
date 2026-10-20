<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Plateam\Partner\Config;

$moduleId = 'plateam.partner';
Loc::loadMessages(__FILE__);

if (!Loader::includeModule($moduleId)) {
    ShowError('Module plateam.partner is not installed');
    return;
}

$flashKey = 'PLATEAM_PARTNER_OPTIONS_FLASH';
$plateamAdminMessage = null;

if (!empty($_SESSION[$flashKey]) && is_array($_SESSION[$flashKey])) {
    $plateamAdminMessage = $_SESSION[$flashKey];
    unset($_SESSION[$flashKey]);
}

$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
    || (isset($REQUEST_METHOD) && $REQUEST_METHOD === 'POST');

if ($isPost && check_bitrix_sessid() && (isset($_POST['save']) || isset($_POST['Apply']) || isset($_POST['apply']))) {
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

    $_SESSION[$flashKey] = [
        'TYPE' => 'OK',
        'MESSAGE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE') . ': saved',
    ];

    // Redirect so the message survives settings.php output buffering / side panel quirks.
    $back = '/bitrix/admin/settings.php?lang=' . urlencode(LANGUAGE_ID)
        . '&mid=' . urlencode($moduleId);
    if (!empty($_REQUEST['mid_menu'])) {
        $back .= '&mid_menu=1';
    }
    if (isset($_REQUEST['IFRAME']) && $_REQUEST['IFRAME'] === 'Y') {
        $back .= '&IFRAME=Y&IFRAME_TYPE=' . urlencode((string) ($_REQUEST['IFRAME_TYPE'] ?? 'SIDE_SLIDER'));
    }
    LocalRedirect($back);
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

$sessId = bitrix_sessid();
$testUrl = '/local/modules/plateam.partner/tools/test_key.php';
?>
<div id="plateam-options-flash" style="margin:0 0 12px;">
<?php if (is_array($plateamAdminMessage)): ?>
    <div style="padding:12px 14px;border-radius:4px;border:1px solid <?= $plateamAdminMessage['TYPE'] === 'OK' ? '#8bc34a' : '#e57373' ?>;background:<?= $plateamAdminMessage['TYPE'] === 'OK' ? '#f1f8e9' : '#ffebee' ?>;color:#222;font-size:14px;">
        <b><?= htmlspecialcharsbx((string) $plateamAdminMessage['MESSAGE']) ?></b>
    </div>
<?php endif; ?>
</div>
<div id="plateam-test-result" style="display:none;margin:0 0 12px;padding:12px 14px;border-radius:4px;border:1px solid #ccc;background:#fafafa;color:#222;font-size:14px;"></div>

<?php
$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE'),
        'TITLE' => Loc::getMessage('PLATEAM_PARTNER_OPTIONS_TITLE'),
    ],
]);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?><?= !empty($_REQUEST['mid_menu']) ? '&mid_menu=1' : '' ?>" id="plateam-partner-options-form">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('PLATEAM_PARTNER_OPT_PARTNER_CODE') ?>:</td>
        <td><input type="text" name="partner_code" id="plateam_partner_code" value="<?= htmlspecialcharsbx($partnerCode) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_API_KEY') ?>:</td>
        <td><input type="password" name="api_key" id="plateam_api_key" value="<?= htmlspecialcharsbx($apiKey) ?>" size="60" autocomplete="off"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_PLATFORM') ?>:</td>
        <td><input type="text" name="platform_origin" id="plateam_platform_origin" value="<?= htmlspecialcharsbx($platformOrigin) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('PLATEAM_PARTNER_OPT_API_BASE') ?>:</td>
        <td><input type="text" name="api_base" id="plateam_api_base" value="<?= htmlspecialcharsbx($apiBase) ?>" size="60"></td>
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
    <input type="submit" name="save" value="<?= Loc::getMessage('PLATEAM_PARTNER_BTN_SAVE') ?>" class="adm-btn-save">
    <input type="button" id="plateam-btn-test-key" value="<?= Loc::getMessage('PLATEAM_PARTNER_BTN_TEST') ?>">
    <?php $tabControl->End(); ?>
</form>
<script>
(function () {
    var btn = document.getElementById('plateam-btn-test-key');
    var box = document.getElementById('plateam-test-result');
    if (!btn || !box) return;

    function show(ok, text) {
        box.style.display = 'block';
        box.style.borderColor = ok ? '#8bc34a' : '#e57373';
        box.style.background = ok ? '#f1f8e9' : '#ffebee';
        box.innerHTML = '<b>' + String(text || '') + '</b>';
        try { box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
    }

    btn.addEventListener('click', function () {
        show(true, '…');
        var body = new FormData();
        body.append('sessid', <?= \Bitrix\Main\Web\Json::encode($sessId) ?>);
        body.append('api_base', (document.getElementById('plateam_api_base') || {}).value || '');
        body.append('api_key', (document.getElementById('plateam_api_key') || {}).value || '');

        fetch(<?= \Bitrix\Main\Web\Json::encode($testUrl) ?>, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.text().then(function (t) {
                var data = null;
                try { data = JSON.parse(t); } catch (e) {}
                if (!data) {
                    show(false, 'Bad response HTTP ' + r.status + ': ' + t.slice(0, 300));
                    return;
                }
                show(!!data.ok, data.message || data.error || JSON.stringify(data));
            });
        }).catch(function (err) {
            show(false, String(err && err.message ? err.message : err));
        });
    });
})();
</script>
<p style="margin-top:8px;color:#888;font-size:11px;">plateam.partner options build 1.1.4</p>
