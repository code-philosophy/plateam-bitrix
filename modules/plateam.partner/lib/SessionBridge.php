<?php

namespace Plateam\Partner;

class SessionBridge
{
    public const SESSION_KEY = 'PLATEAM_CHECKOUT';
    public const RESET_PICK_COOKIE = 'plateam_clear_cert_pick';

    /** @var array<string, string> */
    public const PROP_CODES = [
        'visitorId' => 'PLATEAM_VISITOR_ID',
        'holdId' => 'PLATEAM_HOLD_ID',
        'sesKop' => 'PLATEAM_SES_KOP',
        'uesKop' => 'PLATEAM_UES_KOP',
        'cashKop' => 'PLATEAM_CASH_KOP',
        'userId' => 'PLATEAM_USER_ID',
        'paidSent' => 'PLATEAM_PAID_SENT',
        /** JSON {"sesKop":N,"uesKop":N} from orders/paid — for finish modal */
        'issued' => 'PLATEAM_ISSUED',
    ];

    public static function stashCheckout(array $data): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }
        $prev = self::peekCheckout() ?? [];
        $useSes = array_key_exists('useSes', $data) ? self::truthy($data['useSes']) : !empty($prev['useSes']);
        $useUes = array_key_exists('useUes', $data) ? self::truthy($data['useUes']) : !empty($prev['useUes']);
        $incomingSes = (int) ($data['sesKop'] ?? 0);
        $incomingUes = (int) ($data['uesKop'] ?? 0);
        $hasPick = $useSes || $useUes || $incomingSes > 0 || $incomingUes > 0;
        $explicitOff = array_key_exists('useSes', $data)
            && array_key_exists('useUes', $data)
            && !$useSes
            && !$useUes;
        if (!$hasPick && !$explicitOff && ((int) ($prev['sesKop'] ?? 0) > 0 || (int) ($prev['uesKop'] ?? 0) > 0)) {
            $incomingSes = (int) $prev['sesKop'];
            $incomingUes = (int) $prev['uesKop'];
            $useSes = !empty($prev['useSes']);
            $useUes = !empty($prev['useUes']);
        }
        $visitorId = trim((string) ($data['visitorId'] ?? ''));
        if ($visitorId === '') {
            $visitorId = (string) ($prev['visitorId'] ?? '');
        }
        $userId = trim((string) ($data['userId'] ?? ''));
        if ($userId === '') {
            $userId = (string) ($prev['userId'] ?? '');
        }
        $_SESSION[self::SESSION_KEY] = [
            'visitorId' => $visitorId,
            'userId' => $userId,
            'sesKop' => $incomingSes,
            'uesKop' => $incomingUes,
            'useSes' => $useSes,
            'useUes' => $useUes,
        ];
    }

    /** Только читает stash — не снимает (SOA сохраняет заказ несколько раз). */
    public static function peekCheckout(): ?array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function flagResetPickCookie(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::RESET_PICK_COOKIE, '1', [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCheckout(): void
    {
        if (isset($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    public static function pullCheckout(): ?array
    {
        $data = self::peekCheckout();
        if ($data === null) {
            return null;
        }
        self::clearCheckout();
        return $data;
    }

    public static function readOrderProps(\Bitrix\Sale\Order $order): array
    {
        $out = [];
        $collection = $order->getPropertyCollection();
        foreach (self::PROP_CODES as $key => $code) {
            $prop = $collection->getItemByOrderPropertyCode($code);
            if ($prop) {
                $out[$key] = (string) $prop->getValue();
            }
        }
        return $out;
    }

    public static function writeOrderProp(\Bitrix\Sale\Order $order, string $code, string $value): void
    {
        $collection = $order->getPropertyCollection();
        $prop = $collection->getItemByOrderPropertyCode($code);
        if ($prop) {
            $prop->setValue($value);
        }
    }

    public static function orderIdForApi(\Bitrix\Sale\Order $order): string
    {
        $account = (string) $order->getField('ACCOUNT_NUMBER');
        if ($account !== '') {
            return $account;
        }
        return 'bx-' . (int) $order->getId();
    }

    public static function rubToKop($price): int
    {
        return (int) round((float) $price * 100);
    }

    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        $s = strtolower(trim((string) $value));
        return in_array($s, ['1', 'true', 'y', 'yes', 'on'], true);
    }
}
