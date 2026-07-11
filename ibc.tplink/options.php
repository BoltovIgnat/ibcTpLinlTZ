<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Ibc\Tplink\Helper\ApiLogAccess;

$moduleId = 'ibc.tplink';

if (!Loader::includeModule($moduleId)) {
    return;
}

$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$isSave = $request->isPost() && check_bitrix_sessid();

if ($isSave) {
    Option::set($moduleId, 'source_list_url', (string)$request->getPost('source_list_url'));
    Option::set($moduleId, 'source_sitemap_url', (string)$request->getPost('source_sitemap_url'));
    Option::set($moduleId, 'use_sitemap', $request->getPost('use_sitemap') === 'Y' ? 'Y' : 'N');
    Option::set($moduleId, 'http_timeout', (string)max(5, (int)$request->getPost('http_timeout')));
    Option::set($moduleId, 'http_retries', (string)max(1, (int)$request->getPost('http_retries')));
    Option::set($moduleId, 'request_delay_ms', (string)max(0, (int)$request->getPost('request_delay_ms')));
    Option::set($moduleId, 'site_root', trim((string)$request->getPost('site_root')));
    Option::set($moduleId, 'log_remote_api_base', trim((string)$request->getPost('log_remote_api_base')));

    $logToken = trim((string)$request->getPost('api_log_read_token'));
    if ($logToken !== '') {
        Option::set($moduleId, 'api_log_read_token', $logToken);
    }
    if ($request->getPost('regenerate_log_token') === 'Y') {
        Option::set($moduleId, 'api_log_read_token', bin2hex(random_bytes(24)));
    }

    CAdminMessage::ShowMessage(['MESSAGE' => 'Настройки сохранены', 'TYPE' => 'OK']);
}

if (trim((string)Option::get($moduleId, 'api_log_read_token', '')) === '') {
    ApiLogAccess::ensureTokenExists();
}

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'edit1', 'TAB' => 'Импорт', 'TITLE' => 'Настройки импорта TP-Link'],
    ['DIV' => 'edit2', 'TAB' => 'Логи', 'TITLE' => 'API логов и удалённый сервер'],
]);

$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%">URL списка категории:</td>
        <td width="60%"><input type="text" size="70" name="source_list_url" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'source_list_url')) ?>"></td>
    </tr>
    <tr>
        <td>URL sitemap (kz):</td>
        <td><input type="text" size="70" name="source_sitemap_url" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'source_sitemap_url')) ?>"></td>
    </tr>
    <tr>
        <td>Дополнять список из sitemap:</td>
        <td><input type="checkbox" name="use_sitemap" value="Y" <?= Option::get($moduleId, 'use_sitemap', 'Y') === 'Y' ? 'checked' : '' ?>></td>
    </tr>
    <tr>
        <td>HTTP timeout (сек):</td>
        <td><input type="text" size="10" name="http_timeout" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'http_timeout', '60')) ?>"></td>
    </tr>
    <tr>
        <td>HTTP retries:</td>
        <td><input type="text" size="10" name="http_retries" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'http_retries', '3')) ?>"></td>
    </tr>
    <tr>
        <td>Задержка между запросами (мс):</td>
        <td><input type="text" size="10" name="request_delay_ms" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'request_delay_ms', '200')) ?>"></td>
    </tr>
    <tr>
        <td>Корень раздела на сайте:</td>
        <td><input type="text" size="40" name="site_root" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'site_root', 'ibc/tplink')) ?>"></td>
    </tr>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td>Токен API логов (X-Tplink-Log-Token):</td>
        <td>
            <input type="text" size="70" name="api_log_read_token" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'api_log_read_token')) ?>">
            <br><label><input type="checkbox" name="regenerate_log_token" value="Y"> Сгенерировать новый токен</label>
        </td>
    </tr>
    <tr>
        <td>База удалённого API логов:</td>
        <td>
            <input type="text" size="70" name="log_remote_api_base" value="<?= htmlspecialcharsbx(Option::get($moduleId, 'log_remote_api_base')) ?>">
            <br><span class="adm-info-message-wrap adm-info-message-gray">Для локальной диагностики prod: <code>https://ibcmoney.store/ibc/tplink/api/tplink/v1</code></span>
        </td>
    </tr>
    <tr>
        <td>Файловые логи модуля:</td>
        <td><code>local/modules/ibc.tplink/log/import/</code>, <code>log/api/</code></td>
    </tr>
    <tr>
        <td>JSON прогоны импорта:</td>
        <td><code><?= htmlspecialcharsbx(Option::get($moduleId, 'log_dir', '/local/logs')) ?>/tplink_import_*.json</code></td>
    </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
