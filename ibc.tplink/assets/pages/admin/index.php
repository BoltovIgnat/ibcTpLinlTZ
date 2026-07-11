<?php
// ibc.tplink v1
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('TP-Link Import — Обзор');

use Bitrix\Main\Loader;
use Ibc\Tplink\Controller\Admin\TplinkAdminController;

Loader::includeModule('ibc.tplink');
TplinkAdminController::handleDashboard();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
