<?php

use Ibc\Tplink\Helper\AdminPath;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var string $activeNav */
$activeNav = $activeNav ?? 'dashboard';
$adminBase = AdminPath::adminBase();
$tplPath = defined('SITE_TEMPLATE_PATH') ? SITE_TEMPLATE_PATH : '/local/templates/ibc_tplink_admin';
?>
<div class="tplink-admin" data-tplink-admin>
    <div class="tplink-mesh" aria-hidden="true"></div>
    <div class="tplink-grain" aria-hidden="true"></div>

    <header class="tplink-nav-wrap">
        <nav class="tplink-nav" aria-label="TP-Link admin">
            <a class="tplink-brand" href="<?= htmlspecialcharsbx($adminBase) ?>/">
                <span class="tplink-brand-mark">TP</span>
                <span class="tplink-brand-text">Link Import</span>
            </a>
            <div class="tplink-nav-links">
                <a class="tplink-nav-link <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>" href="<?= htmlspecialcharsbx($adminBase) ?>/">Обзор</a>
                <a class="tplink-nav-link <?= $activeNav === 'logs' ? 'is-active' : '' ?>" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php">Логи</a>
                <a class="tplink-nav-link" href="/bitrix/admin/settings.php?mid=ibc.tplink&lang=<?= LANGUAGE_ID ?>">Настройки</a>
            </div>
            <button type="button" class="tplink-nav-toggle" aria-expanded="false" aria-controls="tplink-mobile-menu">
                <span class="tplink-nav-toggle-bar"></span>
                <span class="tplink-nav-toggle-bar"></span>
            </button>
        </nav>
        <div id="tplink-mobile-menu" class="tplink-mobile-menu" hidden>
            <a href="<?= htmlspecialcharsbx($adminBase) ?>/">Обзор</a>
            <a href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php">Логи</a>
            <a href="/bitrix/admin/settings.php?mid=ibc.tplink&lang=<?= LANGUAGE_ID ?>">Настройки</a>
        </div>
    </header>

    <main class="tplink-main">
