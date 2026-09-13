<?php

namespace Plateam\Partner;

use Bitrix\Main\Loader;
use Bitrix\Sale\DiscountCouponsManager;

/**
 * Промокоды партнёра (настраиваются в options модуля):
 *
 * 1) Нет рефа / своего промо → чужой foreign_promo_code работает как обычная скидка.
 * 2) Свой own_promo_code → активирует сеть (pla_ref).
 * 3) Если включено «не суммировать с рефералкой» (default ON)
 *    и сеть активна / свой промо → чужие купоны снимаются.
 */
class PromoBridge
{
    public const SESSION_OWN = 'PLATEAM_PROMO_OWN';
    public const SESSION_ACTIVATE = 'PLATEAM_PROMO_NEED_ACTIVATE';
    public const SESSION_HOP_SENT = 'PLATEAM_PROMO_HOP_SENT';
    /** Сеть PLATEAM уже активна в этой PHP-сессии (свой промо / реф / welcome). */
    public const SESSION_NETWORK_ACTIVE = 'PLATEAM_NETWORK_ACTIVE';

    public static function register(): void
    {
        $em = \Bitrix\Main\EventManager::getInstance();
        $em->addEventHandler('main', 'OnProlog', [self::class, 'onProlog']);
        $em->addEventHandler('sale', 'OnSaleDiscountCouponApplyed', [self::class, 'onCouponApplied']);
    }

    public static function onProlog(): void
    {
        self::reconcileAppliedCoupons();
    }

    /**
     * Bitrix event (legacy typo: Applyed).
     *
     * @param mixed $coupon
     */
    public static function onCouponApplied($coupon = null): void
    {
        self::reconcileAppliedCoupons();
    }

    public static function ownCouponCode(): string
    {
        return Config::ownPromoCode();
    }

    /** Свой купон сейчас в менеджере купонов (не только session-флаг). */
    public static function hasOwnCouponNow(): bool
    {
        $ownCode = self::ownCouponCode();
        if ($ownCode === '') {
            return false;
        }
        foreach (self::listAppliedCodes() as $code) {
            if (self::codesEqual($code, $ownCode)) {
                return true;
            }
        }
        return false;
    }

    public static function isOwnApplied(): bool
    {
        return self::hasOwnCouponNow() || !empty($_SESSION[self::SESSION_OWN]);
    }

    /**
     * Чужие промо запрещены после активации сети / своего купона,
     * только если в options включено block_foreign_with_referral (default ON).
     * Пока silent + только SHOP10 — false.
     */
    public static function isForeignBlocked(): bool
    {
        if (!Config::blockForeignWithReferral()) {
            return false;
        }
        if (!empty($_SESSION[self::SESSION_NETWORK_ACTIVE]) || !empty($_SESSION[self::SESSION_HOP_SENT])) {
            return true;
        }
        return self::hasOwnCouponNow();
    }

    public static function markNetworkActive(): void
    {
        $_SESSION[self::SESSION_NETWORK_ACTIVE] = 'Y';
    }

    public static function needsActivation(): bool
    {
        return !empty($_SESSION[self::SESSION_ACTIVATE]) && self::hasOwnCouponNow();
    }

    public static function consumeActivationFlag(): bool
    {
        if (empty($_SESSION[self::SESSION_ACTIVATE])) {
            return false;
        }
        unset($_SESSION[self::SESSION_ACTIVATE]);
        $_SESSION[self::SESSION_HOP_SENT] = 'Y';
        self::markNetworkActive();
        return true;
    }

    /** Публичный снимок для компонента checkout / главной. */
    public static function clientState(): array
    {
        $own = self::hasOwnCouponNow();
        return [
            'ownApplied' => $own,
            'ownCode' => self::ownCouponCode(),
            'foreignCode' => Config::foreignPromoCode(),
            'activateRef' => Config::demoRefToken(),
            'needsActivation' => $own && !empty($_SESSION[self::SESSION_ACTIVATE]),
            'foreignBlocked' => self::isForeignBlocked(),
            'blockForeignWithReferral' => Config::blockForeignWithReferral(),
            'networkActive' => !empty($_SESSION[self::SESSION_NETWORK_ACTIVE])
                || !empty($_SESSION[self::SESSION_HOP_SENT]),
            'referralUrl' => Config::goReferralUrl(),
            'stripped' => self::$lastStripped,
        ];
    }

    /** @var list<string> */
    private static array $lastStripped = [];

    /**
     * @return list<string> снятые чужие коды
     */
    public static function reconcileAppliedCoupons(): array
    {
        self::$lastStripped = [];

        if (!Loader::includeModule('sale')) {
            return [];
        }

        try {
            if (method_exists(DiscountCouponsManager::class, 'isStarted')) {
                if (!DiscountCouponsManager::isStarted()) {
                    DiscountCouponsManager::init();
                }
            } else {
                DiscountCouponsManager::init();
            }
        } catch (\Throwable $e) {
            return [];
        }

        $codes = self::listAppliedCodes();
        $ownCode = self::ownCouponCode();
        $hasOwn = false;
        foreach ($codes as $code) {
            if (self::codesEqual($code, $ownCode)) {
                $hasOwn = true;
                break;
            }
        }

        if ($hasOwn) {
            // Свой промокод: активируем сеть и блокируем чужие сразу.
            $_SESSION[self::SESSION_OWN] = 'Y';
            self::markNetworkActive();
            if (empty($_SESSION[self::SESSION_HOP_SENT])) {
                $_SESSION[self::SESSION_ACTIVATE] = 'Y';
            }
        } else {
            unset($_SESSION[self::SESSION_OWN], $_SESSION[self::SESSION_ACTIVATE]);
        }

        // Пока сеть не активна и своего купона нет — чужой (SHOP10) не трогаем.
        if (!self::isForeignBlocked()) {
            return [];
        }

        foreach ($codes as $code) {
            if (self::codesEqual($code, $ownCode)) {
                continue;
            }
            try {
                DiscountCouponsManager::delete($code);
                self::$lastStripped[] = $code;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return self::$lastStripped;
    }

    /** @return list<string> */
    private static function listAppliedCodes(): array
    {
        if (!Loader::includeModule('sale')) {
            return [];
        }

        try {
            if (method_exists(DiscountCouponsManager::class, 'isStarted')) {
                if (!DiscountCouponsManager::isStarted()) {
                    DiscountCouponsManager::init();
                }
            } else {
                DiscountCouponsManager::init();
            }
        } catch (\Throwable $e) {
            return [];
        }

        $list = DiscountCouponsManager::get(true, true);
        if (!is_array($list) || $list === []) {
            return [];
        }

        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['COUPON'] ?? ''));
            if ($code === '') {
                continue;
            }
            $out[] = $code;
        }

        return array_values(array_unique($out));
    }

    private static function codesEqual(string $a, string $b): bool
    {
        return strtoupper(trim($a)) === strtoupper(trim($b));
    }
}
