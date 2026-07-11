<?php

namespace Ibc\Tplink\Access;

final class RoleChecker
{
    public const GROUP_ADMIN = 'ibc_tplink_admin';

    public static function requireAuth(): int
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            self::deny('Требуется авторизация', 401);
        }

        return (int)$USER->GetID();
    }

    public static function requireAdmin(): int
    {
        $userId = self::requireAuth();
        if (self::isAdmin($userId)) {
            return $userId;
        }

        self::deny('Нет доступа к админке TP-Link Import', 403);
    }

    public static function isAdmin(?int $userId = null): bool
    {
        global $USER;
        if (is_object($USER) && method_exists($USER, 'IsAdmin') && $USER->IsAdmin()) {
            return true;
        }

        if ($userId === null) {
            $userId = self::requireAuth();
        }

        return self::userInGroup($userId, self::GROUP_ADMIN);
    }

    private static function userInGroup(int $userId, string $groupCode): bool
    {
        $groupIds = \CUser::GetUserGroup($userId);
        if (!is_array($groupIds)) {
            return false;
        }
        foreach ($groupIds as $groupId) {
            $group = \CGroup::GetByID((int)$groupId)->Fetch();
            if ($group && (string)($group['STRING_ID'] ?? '') === $groupCode) {
                return true;
            }
        }

        return false;
    }

    private static function deny(string $message, int $code): never
    {
        global $APPLICATION;
        if (is_object($APPLICATION)) {
            $APPLICATION->RestartBuffer();
        }
        http_response_code($code);
        echo '<!DOCTYPE html><html lang="ru"><body style="font-family:system-ui;padding:48px;background:#050505;color:#fff;">'
            . '<h1>' . htmlspecialcharsbx($message) . '</h1>'
            . '<p><a href="/bitrix/admin/" style="color:#a78bfa;">Войти в админку Bitrix</a></p></body></html>';
        die();
    }
}
