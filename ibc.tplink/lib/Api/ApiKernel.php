<?php

namespace Ibc\Tplink\Api;

use Bitrix\Main\Context;
use Ibc\Tplink\Controller\Api\V1\LogController;
use Ibc\Tplink\Helper\ApiLogAccess;
use Ibc\Tplink\Helper\JsonResponse;
use Ibc\Tplink\Helper\ModuleFileLogger;
use Ibc\Tplink\Helper\RemoteLogFetcher;

final class ApiKernel
{
    public static function handle(): void
    {
        $request = Context::getCurrent()->getRequest();
        $method = $request->getRequestMethod();
        $path = (string)$request->get('path');

        if ($path === '' || $path === '/') {
            $uri = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
            $path = self::normalizeApiPath($uri);
        }

        $path = '/' . trim($path, '/');

        try {
            self::dispatch($method, $path);
        } catch (\Throwable $e) {
            self::logApiError($path, 'INTERNAL_ERROR', $e->getMessage(), 500, $e);
            JsonResponse::error('INTERNAL_ERROR', $e->getMessage(), 500);
        }
    }

    private static function normalizeApiPath(string $uri): string
    {
        $path = preg_replace('#^/ibc/tplink/api/tplink/v1#', '', $uri) ?? $uri;
        $path = preg_replace('#^/api/tplink/v1#', '', $path) ?? $path;
        $path = preg_replace('#/index\.php$#', '', $path) ?? $path;

        return $path !== '' ? $path : '/';
    }

    private static function logApiError(
        string $path,
        string $code,
        string $message,
        int $httpCode,
        ?\Throwable $previous = null
    ): void {
        $context = [
            'path' => $path,
            'code' => $code,
            'http' => $httpCode,
        ];
        if ($previous !== null) {
            $context['exception'] = $previous::class;
            $context['file'] = $previous->getFile() . ':' . $previous->getLine();
        }

        ModuleFileLogger::channel('api')->error('API error: ' . $message, $context);
    }

    private static function dispatch(string $method, string $path): void
    {
        if ($method === 'GET' && $path === '/admin/logs/channels') {
            LogController::channels();
            return;
        }
        if ($method === 'GET' && $path === '/admin/logs/runs') {
            LogController::runs();
            return;
        }
        if ($method === 'GET' && $path === '/admin/logs/run') {
            LogController::run();
            return;
        }
        if ($method === 'GET' && preg_match('#^/admin/logs/remote/runs$#', $path)) {
            self::remoteRuns();
            return;
        }
        if ($method === 'GET' && preg_match('#^/admin/logs/remote/run$#', $path)) {
            self::remoteRun();
            return;
        }
        if ($method === 'GET' && preg_match('#^/admin/logs/remote/([^/]+)$#', $path, $m)) {
            self::remoteChannel($m[1]);
            return;
        }
        if ($method === 'GET' && preg_match('#^/admin/logs/([^/]+)/dates$#', $path, $m)) {
            LogController::dates($m[1]);
            return;
        }
        if ($method === 'GET' && preg_match('#^/admin/logs/([^/]+)$#', $path, $m)) {
            LogController::read($m[1]);
            return;
        }

        JsonResponse::error('NOT_FOUND', 'Маршрут не найден: ' . $path, 404);
    }

    private static function remoteChannel(string $channel): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $fetcher = new RemoteLogFetcher();
        $data = $fetcher->fetchChannel(
            $channel,
            (int)$request->get('tail') ?: 200,
            $request->get('date') ? (string)$request->get('date') : null,
            $request->get('level') ? (string)$request->get('level') : null,
            $request->get('search') ? (string)$request->get('search') : null
        );

        if ($data === null) {
            JsonResponse::error(
                'REMOTE_LOG_UNAVAILABLE',
                'Не удалось получить логи с ' . $fetcher->getApiBase() . '. Проверьте api_log_read_token и доступность сайта.',
                502
            );
        }

        JsonResponse::send(true, $data, [], [
            'source' => 'remote',
            'remote_base' => $fetcher->getApiBase(),
        ]);
    }

    private static function remoteRuns(): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $fetcher = new RemoteLogFetcher();
        $data = $fetcher->fetchRuns((int)$request->get('limit') ?: 50);
        if ($data === null) {
            JsonResponse::error('REMOTE_LOG_UNAVAILABLE', 'Не удалось получить список прогонов с удалённого сервера.', 502);
        }

        JsonResponse::send(true, $data, [], ['source' => 'remote']);
    }

    private static function remoteRun(): void
    {
        ApiLogAccess::requireAccess();

        $request = Context::getCurrent()->getRequest();
        $file = trim((string)$request->get('file'));
        if ($file === '') {
            JsonResponse::error('VALIDATION_ERROR', 'Parameter file is required', 400);

            return;
        }

        $fetcher = new RemoteLogFetcher();
        $tailItems = $request->get('tail_items');
        $data = $fetcher->fetchRun(
            $file,
            $tailItems !== null && $tailItems !== '' ? (int)$tailItems : null
        );
        if ($data === null) {
            JsonResponse::error('REMOTE_LOG_UNAVAILABLE', 'Не удалось получить прогон с удалённого сервера.', 502);
        }

        JsonResponse::send(true, $data, [], ['source' => 'remote']);
    }
}
