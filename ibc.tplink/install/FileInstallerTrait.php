<?php

trait IbcTplinkFileInstallerTrait
{
    public function installFiles(): bool
    {
        $this->installTplinkTemplates();
        $this->installTplinkPages();

        return true;
    }

    public function uninstallFiles(): bool
    {
        $this->uninstallTplinkPages();

        return true;
    }

    protected function installTplinkTemplates(): void
    {
        $src = $this->getModulePath() . '/assets/templates/ibc_tplink_admin';
        $dst = $_SERVER['DOCUMENT_ROOT'] . '/local/templates/ibc_tplink_admin';
        $this->copyTplinkDir($src, $dst);
    }

    protected function installTplinkPages(): void
    {
        $manifest = $this->getModulePath() . '/assets/pages/manifest.php';
        if (!is_file($manifest)) {
            return;
        }
        $pages = include $manifest;
        if (!is_array($pages)) {
            return;
        }
        foreach ($pages as $page) {
            $src = $this->getModulePath() . '/assets/pages/' . ltrim((string)$page['assetPath'], '/');
            $dst = $this->resolveTplinkSitePath((string)$page['docPath']);
            if (!is_file($src)) {
                continue;
            }
            $dir = dirname($dst);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                continue;
            }
            copy($src, $dst);
        }
    }

    protected function uninstallTplinkPages(): void
    {
        $manifest = $this->getModulePath() . '/assets/pages/manifest.php';
        if (!is_file($manifest)) {
            return;
        }
        $pages = include $manifest;
        if (!is_array($pages)) {
            return;
        }
        foreach ($pages as $page) {
            $dst = $this->resolveTplinkSitePath((string)$page['docPath']);
            if (!is_file($dst)) {
                continue;
            }
            $content = (string)file_get_contents($dst);
            if (str_contains($content, 'ibc.tplink v1')) {
                unlink($dst);
            }
        }
    }

    protected function copyTplinkDir(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    protected function bindTplinkSiteTemplates(): void
    {
        $base = $this->getTplinkSiteUrlPrefix();
        $this->bindTplinkTemplateToPath('ibc_tplink_admin', "CSite::InDir('{$base}/admin/')");
    }

    protected function bindTplinkTemplateToPath(string $templateId, string $condition): void
    {
        $rsSites = \CSite::GetList('sort', 'asc', []);
        while ($site = $rsSites->Fetch()) {
            $siteId = $site['LID'];
            $templates = [];
            $rsTpl = \CSite::GetTemplateList($siteId);
            while ($tpl = $rsTpl->Fetch()) {
                unset($tpl['ID'], $tpl['SITE_ID']);
                if (($tpl['TEMPLATE'] ?? '') === $templateId) {
                    continue;
                }
                $templates[] = $tpl;
            }
            $templates[] = [
                'TEMPLATE' => $templateId,
                'CONDITION' => $condition,
                'SORT' => 1,
            ];
            (new \CSite())->Update($siteId, ['TEMPLATE' => $templates]);
        }
    }

    protected function installTplinkUserGroup(): void
    {
        $existing = \CGroup::GetList('', '', ['STRING_ID' => 'ibc_tplink_admin'])->Fetch();
        if ($existing) {
            return;
        }
        $group = new \CGroup();
        $group->Add([
            'ACTIVE' => 'Y',
            'C_SORT' => 510,
            'NAME' => 'IBC TP-Link Admin',
            'STRING_ID' => 'ibc_tplink_admin',
            'DESCRIPTION' => 'Доступ к веб-админке ibc.tplink',
        ]);
    }
}
