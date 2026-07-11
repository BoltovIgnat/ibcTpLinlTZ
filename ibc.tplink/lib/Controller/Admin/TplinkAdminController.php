<?php

namespace Ibc\Tplink\Controller\Admin;

use Bitrix\Main\Application;
use Ibc\Tplink\Access\RoleChecker;
use Ibc\Tplink\Service\AdminDashboardService;
use Ibc\Tplink\Service\ImportRunGuard;
use Ibc\Tplink\Service\ImportRunLogReader;
use Ibc\Tplink\Service\LogReaderService;

final class TplinkAdminController
{
    public static function handleDashboard(): void
    {
        RoleChecker::requireAdmin();
        $request = Application::getInstance()->getContext()->getRequest();

        $importResult = null;
        $importError = null;
        $guard = new ImportRunGuard();

        if ($request->isPost() && check_bitrix_sessid() && $request->getPost('run_import') === 'Y') {
            try {
                $dryRun = $request->getPost('dry_run') === 'Y';
                $importResult = $guard->run($dryRun);
            } catch (\Throwable $e) {
                $importError = $e->getMessage();
            }
        }

        $data = (new AdminDashboardService())->collect();
        $data['import_locked'] = $guard->isLocked();
        $data['import_locked_until'] = $guard->lockedUntil();
        $activeNav = 'dashboard';
        include dirname(__DIR__, 3) . '/assets/admin/views/dashboard.php';
    }

    public static function handleLogs(): void
    {
        RoleChecker::requireAdmin();
        $request = Application::getInstance()->getContext()->getRequest();

        $tab = (string)($request->get('tab') ?: 'import');
        $tail = max(50, min(2000, (int)($request->get('tail') ?: 200)));
        $date = $request->get('date') ? (string)$request->get('date') : null;
        $level = $request->get('level') ? (string)$request->get('level') : null;
        $search = $request->get('search') ? (string)$request->get('search') : null;
        $runFile = $request->get('run') ? (string)$request->get('run') : '';

        $reader = new LogReaderService();
        $runReader = new ImportRunLogReader();

        $channels = $reader->listChannels();
        $channelData = null;
        $runs = [];
        $runPayload = null;

        if ($tab === 'runs') {
            $runs = $runReader->listRuns(30);
            if ($runFile !== '') {
                $runPayload = $runReader->readRun($runFile, 500);
            }
        } else {
            $channel = in_array($tab, $channels, true) ? $tab : 'import';
            $channelData = $reader->read($channel, $date, $tail, $level, $search);
            $tab = $channel;
        }

        $activeNav = 'logs';
        include dirname(__DIR__, 3) . '/assets/admin/views/logs.php';
    }
}
