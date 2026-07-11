<?php

trait IbcTplinkUrlRewriteInstallerTrait
{
    /** @return list<array{CONDITION: string, RULE: string, ID: string, PATH: string, SORT: int}> */
    protected function getTplinkUrlRewriteRules(): array
    {
        $prefix = $this->getTplinkSiteUrlPrefix();
        $quoted = preg_quote($prefix, '#');

        return [
            [
                'CONDITION' => '#^' . $quoted . '/api/tplink/v1#',
                'RULE' => '',
                'ID' => 'ibc.tplink.api',
                'PATH' => $prefix . '/api/tplink/v1/index.php',
                'SORT' => 100,
            ],
        ];
    }

    /** @return list<string> */
    protected function getTplinkUrlRewriteIds(): array
    {
        return ['ibc.tplink.api', 'ibc.tplink'];
    }

    protected function getTplinkSiteUrlPrefix(): string
    {
        $root = trim((string)\Bitrix\Main\Config\Option::get('ibc.tplink', 'site_root', 'ibc/tplink'), '/');

        return $root !== '' ? '/' . $root : '';
    }

    protected function resolveTplinkSitePath(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $prefix = trim($this->getTplinkSiteUrlPrefix(), '/');

        return rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
            . ($prefix !== '' ? '/' . $prefix : '')
            . ($relative !== '' ? '/' . $relative : '');
    }

    protected function updateTplinkUrlRewrite(): void
    {
        $rules = $this->getTplinkUrlRewriteRules();

        if (class_exists(\CUrlRewriter::class)) {
            $sites = [];
            $rs = \CSite::GetList('', '', ['ACTIVE' => 'Y']);
            while ($row = $rs->Fetch()) {
                $sites[] = (string)$row['LID'];
            }
            if ($sites === []) {
                $sites = ['s1'];
            }

            foreach ($sites as $siteId) {
                foreach ($this->getTplinkUrlRewriteIds() as $legacyId) {
                    \CUrlRewriter::Delete([
                        'ID' => $legacyId,
                        'SITE_ID' => $siteId,
                    ]);
                }
                foreach ($rules as $rule) {
                    \CUrlRewriter::Add(array_merge($rule, ['SITE_ID' => $siteId]));
                }
            }
        }
    }

    protected function uninstallTplinkUrlRewrite(): void
    {
        if (!class_exists(\CUrlRewriter::class)) {
            return;
        }

        $sites = [];
        $rs = \CSite::GetList('', '', ['ACTIVE' => 'Y']);
        while ($row = $rs->Fetch()) {
            $sites[] = (string)$row['LID'];
        }
        if ($sites === []) {
            $sites = ['s1'];
        }

        foreach ($sites as $siteId) {
            foreach ($this->getTplinkUrlRewriteIds() as $id) {
                \CUrlRewriter::Delete([
                    'ID' => $id,
                    'SITE_ID' => $siteId,
                ]);
            }
        }
    }

    protected function installTplinkApiEntry(): void
    {
        $src = $this->getModulePath() . '/assets/api/tplink/v1/index.php';
        if (!is_file($src)) {
            return;
        }

        $dstDir = $this->resolveTplinkSitePath('api/tplink/v1');
        if (!is_dir($dstDir) && !mkdir($dstDir, 0755, true) && !is_dir($dstDir)) {
            return;
        }

        copy($src, $dstDir . '/index.php');

        $htaccess = $dstDir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, <<<'HTA'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
HTA);
        }
    }

    protected function uninstallTplinkApiEntry(): void
    {
        $file = $this->resolveTplinkSitePath('api/tplink/v1/index.php');
        if (is_file($file)) {
            unlink($file);
        }
    }
}
