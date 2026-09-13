<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
$origin = htmlspecialcharsbx($arResult['PLATFORM_ORIGIN']);
$partner = htmlspecialcharsbx($arResult['PARTNER_CODE']);
$ver = htmlspecialcharsbx($arResult['WIDGET_VERSION']);
?>
<script
  src="<?= $origin ?>/widget.js?v=<?= $ver ?>"
  data-partner="<?= $partner ?>"
  data-app="<?= $origin ?>"
  data-pending-finish="/local/modules/plateam.partner/tools/pending_finish.php"
  async
></script>
