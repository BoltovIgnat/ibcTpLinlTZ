<?php

namespace Ibc\Tplink\Controller\Api\V1;

use Bitrix\Main\Context;
use Ibc\Tplink\Helper\ApiLogAccess;
use Ibc\Tplink\Helper\JsonResponse;
use Ibc\Tplink\Service\ImportRunLogReader;
use Ibc\Tplink\Service\LogReaderService;

final class LogController
{
    public static function channels(): void
    {
        ApiLogAccess::requireAccess();

        $reader = new LogReaderService();
        JsonResponse::send(true, [
            'channels' => $reader->listChannels(),
            'default_channel' => 'import',
            'import_runs' => true,
        ]);
    }

    public static function dates(string $channel): void
    {
        ApiLogAccess::requireAccess();

        $reader = new LogReaderService();
        JsonResponse::send(true, [
            'channel' => $channel,
            'dates' => $reader->listDates($channel),
        ]);
    }

    public static function read(string $channel): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $reader = new LogReaderService();
        $data = $reader->read(
            $channel,
            self::nullableString($request->get('date')),
            (int)$request->get('tail') ?: 100,
            self::nullableString($request->get('level')),
            self::nullableString($request->get('search'))
        );

        JsonResponse::send(true, $data, [], [
            'source' => 'local',
            'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
        ]);
    }

    public static function runs(): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $reader = new ImportRunLogReader();
        JsonResponse::send(true, [
            'runs' => $reader->listRuns((int)$request->get('limit') ?: 50),
        ], [], [
            'source' => 'local',
        ]);
    }

    public static function run(): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $file = trim((string)$request->get('file'));
        if ($file === '') {
            JsonResponse::error('VALIDATION_ERROR', 'Parameter file is required', 400);

            return;
        }

        $tailItems = $request->get('tail_items');
        $reader = new ImportRunLogReader();
        $data = $reader->readRun(
            $file,
            $tailItems !== null && $tailItems !== '' ? (int)$tailItems : null
        );

        JsonResponse::send(true, $data, [], [
            'source' => 'local',
        ]);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
