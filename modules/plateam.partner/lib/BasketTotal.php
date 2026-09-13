<?php

namespace Plateam\Partner;

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;

class BasketTotal
{
    /** Сумма корзины текущего пользователя в копейках (0 если Sale недоступен). */
    public static function currentKop(): int
    {
        if (!Loader::includeModule('sale')) {
            return 0;
        }

        $fuserId = (int) Fuser::getId();
        if ($fuserId <= 0) {
            return 0;
        }

        $siteId = defined('SITE_ID') ? (string) SITE_ID : '';
        if ($siteId === '') {
            return 0;
        }

        $basket = Basket::loadItemsForFUser($fuserId, $siteId);
        if (!$basket) {
            return 0;
        }

        return SessionBridge::rubToKop($basket->getPrice());
    }
}
