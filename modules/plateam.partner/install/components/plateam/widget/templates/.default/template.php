<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
$origin = htmlspecialcharsbx($arResult['PLATFORM_ORIGIN']);
$partner = htmlspecialcharsbx($arResult['PARTNER_CODE']);
$ver = htmlspecialcharsbx($arResult['WIDGET_VERSION']);
$stashUrl = htmlspecialcharsbx($arResult['STASH_URL'] ?? '');
?>
<script
  src="<?= $origin ?>/widget.js?v=<?= $ver ?>"
  data-partner="<?= $partner ?>"
  data-app="<?= $origin ?>"
  data-pending-finish="/local/modules/plateam.partner/tools/pending_finish.php"
  async
></script>
<script>
(function () {
  var stashUrl = <?= \Bitrix\Main\Web\Json::encode($arResult['STASH_URL'] ?? '') ?>;
  if (!stashUrl) return;
  function bitrixSessid() {
    try {
      if (window.BX && typeof window.BX.bitrix_sessid === 'function') {
        return String(window.BX.bitrix_sessid() || '');
      }
    } catch (e) {}
    return '';
  }
  function stashMinimal(session) {
    if (!session || !session.visitorId) return;
    var sid = bitrixSessid();
    if (sid === '') return;
    fetch(stashUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Bitrix-Csrf-Token': sid,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        visitorId: session.visitorId,
        userId: session.userId || '',
        checkoutToken: session.checkoutToken || '',
        sesKop: 0,
        uesKop: 0,
        useSes: false,
        useUes: false,
        sessid: sid,
      }),
    }).catch(function () {});
  }
  function hook() {
    if (!window.PLATEAM || typeof window.PLATEAM.onSession !== 'function') {
      window.setTimeout(hook, 300);
      return;
    }
    window.PLATEAM.onSession(stashMinimal);
    if (typeof window.PLATEAM.getSession === 'function') {
      var s = window.PLATEAM.getSession();
      if (s) stashMinimal(s);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hook);
  } else {
    hook();
  }
})();
</script>
