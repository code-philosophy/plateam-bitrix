<?php

namespace Plateam\Partner;

use Bitrix\Main\Config\Option;

class Config
{
    public const MODULE_ID = 'plateam.partner';

    public static function partnerCode(): string
    {
        return trim((string) Option::get(self::MODULE_ID, 'partner_code', ''));
    }

    public static function apiKey(): string
    {
        return trim((string) Option::get(self::MODULE_ID, 'api_key', ''));
    }

    public static function platformOrigin(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'platform_origin', 'https://pla.team'));
        return rtrim($v !== '' ? $v : 'https://pla.team', '/');
    }

    public static function apiBase(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'api_base', ''));
        if ($v !== '') {
            return rtrim($v, '/');
        }
        return self::platformOrigin() . '/api/v0';
    }

    public static function widgetVersion(): string
    {
        return trim((string) Option::get(self::MODULE_ID, 'widget_version', '1.0.0'));
    }

    /** Origin витрины для Partner API (header Origin). */
    public static function siteOrigin(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'site_origin', ''));
        if ($v !== '') {
            return rtrim($v, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host !== '' ? $scheme . '://' . $host : '';
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '' && self::partnerCode() !== '' && self::platformOrigin() !== '';
    }

    /** Собственный промокод партнёра (активация сети). Пусто = не задан. */
    public static function ownPromoCode(): string
    {
        return strtoupper(trim((string) Option::get(self::MODULE_ID, 'own_promo_code', '')));
    }

    /** Чужой промокод магазина (скидка Sale). Пусто = не задан. */
    public static function foreignPromoCode(): string
    {
        return strtoupper(trim((string) Option::get(self::MODULE_ID, 'foreign_promo_code', '')));
    }

    /** Токен реферальной ссылки go (/r/{token}). */
    public static function demoRefToken(): string
    {
        return trim((string) Option::get(self::MODULE_ID, 'demo_ref_token', ''));
    }

    public static function goOrigin(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'go_origin', 'https://go.pla.team'));
        return rtrim($v !== '' ? $v : 'https://go.pla.team', '/');
    }

    public static function goReferralUrl(): string
    {
        $token = self::demoRefToken();
        if ($token === '') {
            return '';
        }
        return self::goOrigin() . '/r/' . rawurlencode($token);
    }

    /**
     * Галочка модуля: чужой промокод не суммируется с рефералкой / своим промо.
     * По умолчанию включено (Y).
     */
    public static function blockForeignWithReferral(): bool
    {
        $v = strtoupper(trim((string) Option::get(self::MODULE_ID, 'block_foreign_with_referral', 'Y')));
        return $v !== 'N' && $v !== '0' && $v !== '';
    }
}
