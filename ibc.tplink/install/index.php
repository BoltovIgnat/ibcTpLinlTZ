<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Ibc\Tplink\Helper\ApiLogAccess;
use Ibc\Tplink\Service\IblockInstaller;

require_once __DIR__ . '/UrlRewriteInstallerTrait.php';
require_once __DIR__ . '/FileInstallerTrait.php';

class ibc_tplink extends CModule
{
    use IbcTplinkUrlRewriteInstallerTrait;
    use IbcTplinkFileInstallerTrait;
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $langPath = __DIR__ . '/../lang/' . (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru') . '/install/index.php';
        if (is_file($langPath)) {
            include $langPath;
        }

        $this->MODULE_ID = 'ibc.tplink';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = GetMessage('IBC_TPLINK_MODULE_NAME') ?: 'IBC TP-Link Import';
        $this->MODULE_DESCRIPTION = GetMessage('IBC_TPLINK_MODULE_DESC') ?: 'CLI-импорт каталога TP-Link из tp-link.com';
        $this->PARTNER_NAME = 'IBC';
        $this->PARTNER_URI = '';
    }

    public function DoInstall(): void
    {
        global $APPLICATION;
        if (!CheckVersion(ModuleManager::getVersion('main'), '20.0.0')) {
            $APPLICATION->ThrowException('Требуется main >= 20.0.0');

            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);
        $this->installOptions();
        $this->installIblock();
        $this->installFiles();
        $this->installTplinkUserGroup();
        $this->bindTplinkSiteTemplates();
        $this->installTplinkApiEntry();
        $this->updateTplinkUrlRewrite();
        ApiLogAccess::ensureTokenExists();
        if (Option::get($this->MODULE_ID, 'log_remote_api_base', '') === '') {
            Option::set($this->MODULE_ID, 'log_remote_api_base', 'https://ibcmoney.store/ibc/tplink/api/tplink/v1');
        }
    }

    public function DoUpdate(): bool
    {
        Loader::includeModule($this->MODULE_ID);
        $this->installOptions();
        $this->installIblock();
        $this->installFiles();
        $this->installTplinkUserGroup();
        $this->bindTplinkSiteTemplates();
        $this->installTplinkApiEntry();
        $this->updateTplinkUrlRewrite();
        ApiLogAccess::ensureTokenExists();
        if (Option::get($this->MODULE_ID, 'log_remote_api_base', '') === '') {
            Option::set($this->MODULE_ID, 'log_remote_api_base', 'https://ibcmoney.store/ibc/tplink/api/tplink/v1');
        }
        Option::set($this->MODULE_ID, '~version', $this->MODULE_VERSION);

        return true;
    }

    public function DoUninstall(): void
    {
        Loader::includeModule($this->MODULE_ID);
        $this->uninstallFiles();
        $this->uninstallTplinkApiEntry();
        $this->uninstallTplinkUrlRewrite();
        $this->uninstallOptions();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    protected function installOptions(): void
    {
        $ibc_tplink_default_option = [];
        include $this->getModulePath() . '/default_option.php';
        foreach ($ibc_tplink_default_option as $key => $value) {
            if (Option::get($this->MODULE_ID, $key, '') === '') {
                Option::set($this->MODULE_ID, $key, (string)$value);
            }
        }
    }

    protected function uninstallOptions(): void
    {
        Option::delete($this->MODULE_ID);
    }

    protected function installIblock(): void
    {
        if (!Loader::includeModule('iblock')) {
            return;
        }
        (new IblockInstaller())->ensureCatalog();
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
