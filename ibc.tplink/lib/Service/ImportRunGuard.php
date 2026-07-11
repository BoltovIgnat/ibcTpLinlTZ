<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Ibc\Tplink\Helper\ImportLog;

final class ImportRunGuard
{
    private const MODULE_ID = 'ibc.tplink';

    private const OPTION_LOCK = 'import_run_lock_until';

    private const LOCK_TTL_SEC = 7200;

    public function acquire(): bool
    {
        if ($this->isLocked()) {
            return false;
        }
        Option::set(self::MODULE_ID, self::OPTION_LOCK, (string)(time() + self::LOCK_TTL_SEC));

        return true;
    }

    public function release(): void
    {
        Option::set(self::MODULE_ID, self::OPTION_LOCK, '0');
    }

    public function isLocked(): bool
    {
        $until = (int)Option::get(self::MODULE_ID, self::OPTION_LOCK, '0');

        return $until > time();
    }

    public function lockedUntil(): int
    {
        return max(0, (int)Option::get(self::MODULE_ID, self::OPTION_LOCK, '0'));
    }

    /**
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false): array
    {
        if (!$this->acquire()) {
            throw new \RuntimeException('Импорт уже выполняется. Подождите завершения текущего прогона.');
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        ImportLog::info('Web import started', ['dry_run' => $dryRun, 'user_id' => self::currentUserId()]);

        $released = false;
        $release = function () use (&$released): void {
            if ($released) {
                return;
            }
            $released = true;
            $this->release();
        };
        register_shutdown_function($release);

        try {
            $result = ImportServiceFactory::create()->run($dryRun);
            $result['trigger'] = 'web_admin';

            return $result;
        } catch (\Throwable $e) {
            ImportLog::error('Web import failed', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            $release();
        }
    }

    private static function currentUserId(): int
    {
        global $USER;

        return is_object($USER) ? (int)$USER->GetID() : 0;
    }
}
