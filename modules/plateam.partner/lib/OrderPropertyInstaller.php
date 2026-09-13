<?php

namespace Plateam\Partner;

use CSaleOrderProps;
use CSaleOrderPropsGroup;

class OrderPropertyInstaller
{
    /** @var array<int, array{CODE: string, NAME: string, TYPE: string, DEFAULT: string}> */
    private const PROPS = [
        ['CODE' => 'PLATEAM_VISITOR_ID', 'NAME' => 'PLATEAM visitorId', 'TYPE' => 'STRING', 'DEFAULT' => ''],
        ['CODE' => 'PLATEAM_USER_ID', 'NAME' => 'PLATEAM userId', 'TYPE' => 'STRING', 'DEFAULT' => ''],
        ['CODE' => 'PLATEAM_HOLD_ID', 'NAME' => 'PLATEAM holdId', 'TYPE' => 'STRING', 'DEFAULT' => ''],
        ['CODE' => 'PLATEAM_SES_KOP', 'NAME' => 'PLATEAM СЭС (коп.)', 'TYPE' => 'STRING', 'DEFAULT' => '0'],
        ['CODE' => 'PLATEAM_UES_KOP', 'NAME' => 'PLATEAM УЭС (коп.)', 'TYPE' => 'STRING', 'DEFAULT' => '0'],
        ['CODE' => 'PLATEAM_CASH_KOP', 'NAME' => 'PLATEAM cash (коп.)', 'TYPE' => 'STRING', 'DEFAULT' => '0'],
        ['CODE' => 'PLATEAM_PAID_SENT', 'NAME' => 'PLATEAM paid sent', 'TYPE' => 'STRING', 'DEFAULT' => ''],
        ['CODE' => 'PLATEAM_ISSUED', 'NAME' => 'PLATEAM issued (JSON)', 'TYPE' => 'STRING', 'DEFAULT' => ''],
    ];

    public static function install(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('sale')) {
            return;
        }

        $personTypes = [];
        $rs = \CSalePersonType::GetList([], false, false, false, ['ID']);
        while ($row = $rs->Fetch()) {
            $personTypes[] = (int) $row['ID'];
        }
        if (!$personTypes) {
            $personTypes = [1];
        }

        foreach ($personTypes as $personTypeId) {
            $groupId = self::ensureGroup($personTypeId);
            foreach (self::PROPS as $def) {
                self::ensureProp($personTypeId, $groupId, $def);
            }
        }
    }

    private static function ensureGroup(int $personTypeId): int
    {
        $rs = CSaleOrderPropsGroup::GetList(
            ['SORT' => 'ASC'],
            ['PERSON_TYPE_ID' => $personTypeId, 'NAME' => 'PLATEAM'],
        );
        if ($row = $rs->Fetch()) {
            return (int) $row['ID'];
        }
        $id = CSaleOrderPropsGroup::Add([
            'PERSON_TYPE_ID' => $personTypeId,
            'NAME' => 'PLATEAM',
            'SORT' => 900,
        ]);
        return (int) $id;
    }

    private static function ensureProp(int $personTypeId, int $groupId, array $def): void
    {
        $rs = CSaleOrderProps::GetList(
            [],
            ['PERSON_TYPE_ID' => $personTypeId, 'CODE' => $def['CODE']],
        );
        if ($rs->Fetch()) {
            return;
        }
        CSaleOrderProps::Add([
            'PERSON_TYPE_ID' => $personTypeId,
            'PROPS_GROUP_ID' => $groupId,
            'NAME' => $def['NAME'],
            'TYPE' => $def['TYPE'],
            'REQUIRED' => 'N',
            'SORT' => 100,
            'CODE' => $def['CODE'],
            'DEFAULT_VALUE' => $def['DEFAULT'],
            'USER_PROPS' => 'N',
            'IS_LOCATION' => 'N',
            'IS_EMAIL' => 'N',
            'IS_PROFILE_NAME' => 'N',
            'IS_PAYER' => 'N',
            'IS_ZIP' => 'N',
            'IS_PHONE' => 'N',
            'ACTIVE' => 'Y',
            'UTIL' => 'Y',
        ]);
    }
}
