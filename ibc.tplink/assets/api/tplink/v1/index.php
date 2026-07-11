<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Ibc\Tplink\Api\ApiKernel;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Tplink-Log-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    return;
}

if (!Loader::includeModule('ibc.tplink')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'data' => new stdClass(),
        'meta' => new stdClass(),
        'errors' => [['code' => 'MODULE_ERROR', 'message' => 'Модуль ibc.tplink не установлен']],
    ], JSON_UNESCAPED_UNICODE);
    return;
}

ApiKernel::handle();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
