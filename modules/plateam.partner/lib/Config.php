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
        return rtrim($v, '/');
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
        return trim((string) Option::get(self::MODULE_ID, 'widget_version', '20260913h'));
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

    /** Наш демо-промокод (активация сети, без денежной скидки Sale). */
    public static function ownPromoCode(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'own_promo_code', 'PLATEAM'));
        return $v !== '' ? strtoupper($v) : 'PLATEAM';
    }

    /** Чужой демо-промокод магазина (−10%). */
    public static function foreignPromoCode(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'foreign_promo_code', 'SHOP10'));
        return $v !== '' ? strtoupper($v) : 'SHOP10';
    }

    /** Реф-токен go для east / партнёра north. */
    public static function demoRefToken(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'demo_ref_token', 'demo-ref-north'));
        return $v !== '' ? $v : 'demo-ref-north';
    }

    public static function goOrigin(): string
    {
        $v = trim((string) Option::get(self::MODULE_ID, 'go_origin', 'https://go.demo.pla.team'));
        return rtrim($v !== '' ? $v : 'https://go.demo.pla.team', '/');
    }

    public static function goReferralUrl(): string
    {
        return self::goOrigin() . '/r/' . rawurlencode(self::demoRefToken());
    }

    /**
     * Галочка модуля: чужой промокод не суммируется с рефералкой / своим PLATEAM.
     * По умолчанию включено (Y).
     */
    public static function blockForeignWithReferral(): bool
    {
        $v = strtoupper(trim((string) Option::get(self::MODULE_ID, 'block_foreign_with_referral', 'Y')));
        return $v !== 'N' && $v !== '0' && $v !== '';
    }
}
