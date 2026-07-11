<?php

namespace Ibc\Tplink\Helper;

use Bitrix\Main\Web\Json;

final class JsonResponse
{
    public static function send(bool $success, $data = null, array $errors = [], array $meta = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        echo Json::encode([
            'success' => $success,
            'data' => $data ?? new \stdClass(),
            'meta' => $meta ?: new \stdClass(),
            'errors' => $errors,
        ]);
    }

    public static function error(string $code, string $message, int $httpCode = 400, array $fields = []): void
    {
        self::send(false, null, [[
            'code' => $code,
            'message' => $message,
            'fields' => $fields ?: new \stdClass(),
        ]], [], $httpCode);
    }
}
