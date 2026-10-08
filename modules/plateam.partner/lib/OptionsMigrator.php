<?php

namespace Plateam\Partner;

use Bitrix\Main\Config\Option;

/**
 * Normalize leftover b_option after uninstall→reinstall (options are not deleted on uninstall).
 */
class OptionsMigrator
{
    public const MARKER = 'options_migrated_v1';

    public static function migrate(): void
    {
        $moduleId = Config::MODULE_ID;

        $platform = trim((string) Option::get($moduleId, 'platform_origin', ''));
        $apiBase = trim((string) Option::get($moduleId, 'api_base', ''));
        $goOrigin = trim((string) Option::get($moduleId, 'go_origin', ''));

        if (self::isDemoHost($platform) || self::isDemoHost($apiBase) || self::isDemoHost($goOrigin)) {
            Option::set($moduleId, 'platform_origin', 'https://pla.team');
            Option::set($moduleId, 'api_base', 'https://pla.team/api/v0');
            Option::set($moduleId, 'go_origin', 'https://go.pla.team');
        }

        $apiKey = trim((string) Option::get($moduleId, 'api_key', ''));
        if ($apiKey !== '' && !self::isModernPartnerKey($apiKey)) {
            Option::set($moduleId, 'api_key', '');
        }

        Option::set($moduleId, self::MARKER, 'Y');
    }

    private static function isDemoHost(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        return stripos($value, 'demo.pla.team') !== false;
    }

    /** Sandbox/live keys from partner cabinet on pla.team. */
    private static function isModernPartnerKey(string $key): bool
    {
        return str_starts_with($key, 'pk_test_') || str_starts_with($key, 'pk_live_');
    }
}
