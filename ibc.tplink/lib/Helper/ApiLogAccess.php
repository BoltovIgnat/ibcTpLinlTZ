<?php

namespace Ibc\Tplink\Helper;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;

/**
 * Доступ к admin API логов (админ Bitrix или токен X-Tplink-Log-Token).
 */
final class ApiLogAccess
{
    private const MODULE_ID = 'ibc.tplink';

    private const OPTION_TOKEN = 'api_log_read_token';

    public static function requireAccess(): void
    {
        if (self::hasAccess()) {
            return;
        }

        JsonResponse::error(
            'FORBIDDEN',
            'Доступ к логам запрещён. Нужен токен X-Tplink-Log-Token или сессия администратора.',
            403
        );
    }

    public static function hasAccess(): bool
    {
        global $USER;
        if (is_object($USER) && method_exists($USER, 'IsAdmin') && $USER->IsAdmin()) {
            return true;
        }

        $request = Context::getCurrent()->getRequest();
        $token = trim((string)($request->getHeader('X-Tplink-Log-Token') ?? ''));
        if ($token === '') {
            $token = trim((string)$request->get('token'));
        }

        $expected = trim((string)Option::get(self::MODULE_ID, self::OPTION_TOKEN, ''));
        if ($expected === '' || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function ensureTokenExists(): string
    {
        $existing = trim((string)Option::get(self::MODULE_ID, self::OPTION_TOKEN, ''));
        if ($existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(24));
        Option::set(self::MODULE_ID, self::OPTION_TOKEN, $token);

        return $token;
    }
}
