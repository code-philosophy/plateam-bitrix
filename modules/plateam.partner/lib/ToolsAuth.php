<?php

namespace Plateam\Partner;

class ToolsAuth
{
    public static function requireSessid(): void
    {
        if (!check_bitrix_sessid()) {
            self::deny('invalid_sessid', 403);
        }
    }

    public static function requireAdmin(): void
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            self::deny('auth_required', 403);
        }
        if ($USER->IsAdmin()) {
            return;
        }
        $right = (string) $USER->GetUserRight('plateam.partner');
        if ($right === 'W' || $right === 'X') {
            return;
        }
        self::deny('admin_required', 403);
    }

    public static function deny(string $error, int $code = 403): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
        die();
    }
}
