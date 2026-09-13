<?php

/**
 * Пилот east: два промокода — SHOP10 (−10%) и PLATEAM (маркер активации сети).
 * Запуск: /local/modules/plateam.partner/tools/setup_demo_promocodes.php
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\DiscountCouponTable;
use Bitrix\Sale\Internals\DiscountTable;

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('sale')) {
    echo json_encode(['ok' => false, 'error' => 'sale module missing'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    die();
}

$siteId = defined('SITE_ID') ? (string) SITE_ID : 's1';
$lid = $siteId !== '' ? $siteId : 's1';

$userGroups = [];
$groupRes = CGroup::GetList('c_sort', 'asc', ['ACTIVE' => 'Y']);
while ($g = $groupRes->Fetch()) {
    $userGroups[] = (int) $g['ID'];
}
if ($userGroups === []) {
    $userGroups = [2];
}

$defs = [
    [
        'xmlId' => 'PLATEAM_DEMO_FOREIGN',
        'name' => 'Магазин: промокод SHOP10 (−10%)',
        'coupon' => 'SHOP10',
        'percent' => 10.0,
        'priority' => 100,
        'lastDiscount' => 'N',
    ],
    [
        'xmlId' => 'PLATEAM_DEMO_OWN',
        // Пустое имя — иначе Bitrix дописывает «(Сеть: …)» к статусу купона.
        'name' => 'PLATEAM',
        'coupon' => 'PLATEAM',
        'percent' => 0.0,
        'priority' => 1,
        // Не LAST_DISCOUNT: иначе Bitrix может глушить SHOP10 до активации сети.
        // Взаимоисключение — только PromoBridge после активации / своего купона.
        'lastDiscount' => 'N',
    ],
];

/**
 * @return array{CLASS_ID:string,DATA:array,CHILDREN:array}
 */
function plateamDiscountConditionsAlways(): array
{
    return [
        'CLASS_ID' => 'CondGroup',
        'DATA' => [
            'All' => 'AND',
            'True' => 'True',
        ],
        'CHILDREN' => [],
    ];
}

/**
 * @return array{CLASS_ID:string,DATA:array,CHILDREN:array}
 */
function plateamDiscountActionsPercent(float $percent): array
{
    return [
        'CLASS_ID' => 'CondGroup',
        'DATA' => [
            'All' => 'AND',
        ],
        'CHILDREN' => [
            [
                'CLASS_ID' => 'ActSaleBsktGrp',
                'DATA' => [
                    'Type' => 'Discount',
                    'Value' => $percent,
                    'Unit' => 'Perc',
                    'Max' => 0,
                    'All' => 'AND',
                    'True' => 'True',
                ],
                'CHILDREN' => [],
            ],
        ],
    ];
}

/**
 * @param list<int> $userGroups
 * @return array{discountId:int,couponId:int|null,created:bool,updated:bool,error:?string}
 */
function plateamUpsertPromoDiscount(
    string $lid,
    array $userGroups,
    string $xmlId,
    string $name,
    string $couponCode,
    float $percent,
    int $priority,
    string $lastDiscount
): array {
    $discountId = 0;
    $created = false;
    $updated = false;

    $existing = CSaleDiscount::GetList(
        [],
        ['XML_ID' => $xmlId, 'LID' => $lid],
        false,
        ['nTopCount' => 1],
        ['ID', 'XML_ID']
    );
    if ($row = $existing->Fetch()) {
        $discountId = (int) $row['ID'];
    }

    $fields = [
        'LID' => $lid,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'SORT' => $priority,
        'PRIORITY' => $priority,
        'LAST_DISCOUNT' => $lastDiscount,
        'LAST_LEVEL_DISCOUNT' => 'N',
        'XML_ID' => $xmlId,
        'CURRENCY' => 'RUB',
        'USER_GROUPS' => $userGroups,
        'CONDITIONS' => plateamDiscountConditionsAlways(),
        'ACTIONS' => plateamDiscountActionsPercent($percent),
        'USE_COUPONS' => 'Y',
    ];

    if ($discountId > 0) {
        if (!CSaleDiscount::Update($discountId, $fields)) {
            global $APPLICATION;
            $err = is_object($APPLICATION) ? (string) $APPLICATION->GetException() : 'update failed';
            return [
                'discountId' => $discountId,
                'couponId' => null,
                'created' => false,
                'updated' => false,
                'error' => $err !== '' ? $err : 'CSaleDiscount::Update failed',
            ];
        }
        $updated = true;
    } else {
        $discountId = (int) CSaleDiscount::Add($fields);
        if ($discountId <= 0) {
            global $APPLICATION;
            $err = is_object($APPLICATION) ? (string) $APPLICATION->GetException() : 'add failed';
            return [
                'discountId' => 0,
                'couponId' => null,
                'created' => false,
                'updated' => false,
                'error' => $err !== '' ? $err : 'CSaleDiscount::Add failed',
            ];
        }
        $created = true;
    }

    DiscountTable::setUseCoupons([$discountId], true);

    $couponId = null;
    $couponRow = DiscountCouponTable::getList([
        'filter' => ['=COUPON' => $couponCode],
        'select' => ['ID', 'DISCOUNT_ID', 'ACTIVE'],
        'limit' => 1,
    ])->fetch();

    if ($couponRow) {
        $couponId = (int) $couponRow['ID'];
        $upd = DiscountCouponTable::update($couponId, [
            'DISCOUNT_ID' => $discountId,
            'ACTIVE' => 'Y',
            'TYPE' => DiscountCouponTable::TYPE_MULTI_ORDER,
            'MAX_USE' => 0,
            'ACTIVE_FROM' => null,
            'ACTIVE_TO' => null,
        ]);
        if (!$upd->isSuccess()) {
            return [
                'discountId' => $discountId,
                'couponId' => $couponId,
                'created' => $created,
                'updated' => $updated,
                'error' => implode('; ', $upd->getErrorMessages()),
            ];
        }
    } else {
        $add = DiscountCouponTable::add([
            'DISCOUNT_ID' => $discountId,
            'ACTIVE' => 'Y',
            'COUPON' => $couponCode,
            'TYPE' => DiscountCouponTable::TYPE_MULTI_ORDER,
            'MAX_USE' => 0,
            'ACTIVE_FROM' => null,
            'ACTIVE_TO' => null,
            'DESCRIPTION' => $name,
        ]);
        if (!$add->isSuccess()) {
            return [
                'discountId' => $discountId,
                'couponId' => null,
                'created' => $created,
                'updated' => $updated,
                'error' => implode('; ', $add->getErrorMessages()),
            ];
        }
        $couponId = (int) $add->getId();
    }

    return [
        'discountId' => $discountId,
        'couponId' => $couponId,
        'created' => $created,
        'updated' => $updated,
        'error' => null,
    ];
}

$out = [
    'ok' => true,
    'lid' => $lid,
    'promos' => [],
];

foreach ($defs as $def) {
    $result = plateamUpsertPromoDiscount(
        $lid,
        $userGroups,
        $def['xmlId'],
        $def['name'],
        $def['coupon'],
        $def['percent'],
        $def['priority'],
        $def['lastDiscount']
    );
    $item = [
        'coupon' => $def['coupon'],
        'xmlId' => $def['xmlId'],
        'percent' => $def['percent'],
        'discountId' => $result['discountId'],
        'couponId' => $result['couponId'],
        'created' => $result['created'],
        'updated' => $result['updated'],
    ];
    if ($result['error'] !== null) {
        $out['ok'] = false;
        $item['error'] = $result['error'];
    }
    $out['promos'][] = $item;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
