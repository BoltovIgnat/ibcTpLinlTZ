<?php
// ibc.tplink v1
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('TP-Link Import — Логи');

use Bitrix\Main\Loader;
use Ibc\Tplink\Controller\Admin\TplinkAdminController;

Loader::includeModule('ibc.tplink');
TplinkAdminController::handleLogs();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
