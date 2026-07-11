<?php

namespace Ibc\Tplink\Helper;

use Bitrix\Main\Config\Option;

final class AdminPath
{
    public static function sitePrefix(): string
    {
        $root = trim((string)Option::get('ibc.tplink', 'site_root', 'ibc/tplink'), '/');

        return $root !== '' ? '/' . $root : '';
    }

    public static function adminBase(): string
    {
        return self::sitePrefix() . '/admin';
    }

    public static function url(string $page = ''): string
    {
        $page = ltrim($page, '/');
        $base = self::adminBase();

        return $page === '' ? $base . '/' : $base . '/' . $page;
    }
}
