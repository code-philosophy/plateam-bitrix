<?php

namespace Plateam\Partner;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\ServiceRestrictionTable;
use Bitrix\Sale\PaySystem\Manager;

class PaySystemInstaller
{
    public const HANDLER_CODE = 'plateam_stub';
    public const PAY_SYSTEM_NAME = 'PLATEAM тест (стенд)';

    /** @return list<string> */
    public static function install(): array
    {
        $steps = [];
        self::copyHandlerFiles();
        $steps[] = 'handler files → /local/php_interface/include/sale_payment/plateam_stub';

        if (!Loader::includeModule('sale')) {
            $steps[] = 'ERROR: sale module not loaded';
            return $steps;
        }

        $siteId = defined('SITE_ID') && SITE_ID !== '' ? SITE_ID : 's1';
        $personTypeIds = self::personTypeIds($siteId);
        $steps[] = 'person types: ' . implode(',', $personTypeIds);

        $paySystemId = self::ensurePaySystem($personTypeIds);
        $steps[] = 'pay system id=' . $paySystemId;

        $disabled = self::deactivateOtherPaySystems($paySystemId);
        $steps[] = 'deactivated other pay systems: ' . $disabled;

        return $steps;
    }

    /** @return list<int> */
    private static function personTypeIds(string $siteId): array
    {
        $ids = [];
        $dbPt = \CSalePersonType::GetList(['SORT' => 'ASC'], ['LID' => $siteId, 'ACTIVE' => 'Y']);
        while ($pt = $dbPt->Fetch()) {
            $ids[] = (int) $pt['ID'];
        }
        if ($ids === []) {
            throw new \RuntimeException('No person types for site ' . $siteId);
        }
        return $ids;
    }

    private static function copyHandlerFiles(): void
    {
        $src = __DIR__ . '/sale_payment/' . self::HANDLER_CODE;
        $dst = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/sale_payment/' . self::HANDLER_CODE;
        if (!is_dir($src)) {
            throw new \RuntimeException('Handler source missing: ' . $src);
        }
        self::copyDir($src, $dst);
    }

    private static function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($it as $item) {
            $target = $dst . '/' . $it->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    /** @param list<int> $personTypeIds */
    private static function ensurePaySystem(array $personTypeIds): int
    {
        $existingId = self::findPaySystemId();
        $fields = [
            'NAME' => self::PAY_SYSTEM_NAME,
            'PSA_NAME' => self::PAY_SYSTEM_NAME,
            'ACTIVE' => 'Y',
            'SORT' => 100,
            'DESCRIPTION' => 'Staging: имитация банка, без списания.',
            'ACTION_FILE' => self::HANDLER_CODE,
            'ENTITY_REGISTRY_TYPE' => 'ORDER',
            'NEW_WINDOW' => 'N',
            'HAVE_PAYMENT' => 'Y',
            'HAVE_ACTION' => 'N',
            'HAVE_RESULT' => 'N',
            'HAVE_PREPAY' => 'N',
            'HAVE_RESULT_RECEIVE' => 'Y',
            'ALLOW_EDIT_PAYMENT' => 'Y',
            'IS_CASH' => 'N',
            'ENCODING' => 'utf-8',
        ];

        if ($existingId > 0) {
            Manager::update($existingId, $fields);
            $paySystemId = $existingId;
        } else {
            $add = Manager::add($fields);
            if (!$add->isSuccess()) {
                throw new \RuntimeException(implode('; ', $add->getErrorMessages()));
            }
            $paySystemId = (int) $add->getId();
        }

        Manager::update($paySystemId, [
            'PAY_SYSTEM_ID' => $paySystemId,
            'PERSON_TYPE_ID' => null,
        ]);

        self::removeBrokenLegacyRows($paySystemId);
        self::ensureRestrictions($paySystemId, $personTypeIds);

        return $paySystemId;
    }

    private static function findPaySystemId(): int
    {
        $ids = [];
        $db = Manager::getList([
            'filter' => ['=ACTION_FILE' => self::HANDLER_CODE],
            'select' => ['ID', 'PAY_SYSTEM_ID'],
            'order' => ['ID' => 'ASC'],
        ]);
        while ($row = $db->fetch()) {
            $ids[] = $row;
        }
        foreach ($ids as $row) {
            if (!empty($row['PAY_SYSTEM_ID'])) {
                return (int) $row['ID'];
            }
        }
        return isset($ids[0]['ID']) ? (int) $ids[0]['ID'] : 0;
    }

    /** Удаляет ошибочные записи CSalePaySystem (id 10/11 без PAY_SYSTEM_ID). */
    private static function removeBrokenLegacyRows(int $keepId): void
    {
        $db = Manager::getList([
            'filter' => ['=ACTION_FILE' => self::HANDLER_CODE],
            'select' => ['ID', 'PAY_SYSTEM_ID'],
        ]);
        while ($row = $db->fetch()) {
            $id = (int) $row['ID'];
            if ($id === $keepId) {
                continue;
            }
            Manager::delete($id);
        }
    }

    /** @param list<int> $personTypeIds */
    private static function ensureRestrictions(int $paySystemId, array $personTypeIds): void
    {
        $serviceType = \Bitrix\Sale\Services\PaySystem\Restrictions\Manager::SERVICE_TYPE_PAYMENT;
        $existing = [];
        $res = ServiceRestrictionTable::getList([
            'filter' => ['=SERVICE_ID' => $paySystemId, '=SERVICE_TYPE' => $serviceType],
        ]);
        while ($row = $res->fetch()) {
            $existing[$row['CLASS_NAME']] = $row;
        }

        $defs = [
            '\\Bitrix\\Sale\\Services\\PaySystem\\Restrictions\\PersonType' => [
                'PERSON_TYPE_ID' => $personTypeIds,
            ],
            '\\Bitrix\\Sale\\Services\\PaySystem\\Restrictions\\Currency' => [
                'CURRENCY' => 'RUB',
            ],
        ];

        foreach ($defs as $className => $params) {
            if (isset($existing[$className])) {
                ServiceRestrictionTable::update((int) $existing[$className]['ID'], ['PARAMS' => $params]);
                continue;
            }
            ServiceRestrictionTable::add([
                'SERVICE_ID' => $paySystemId,
                'SERVICE_TYPE' => $serviceType,
                'SORT' => 100,
                'CLASS_NAME' => $className,
                'PARAMS' => $params,
            ]);
        }
    }

    private static function deactivateOtherPaySystems(int $keepId): int
    {
        $count = 0;
        $db = Manager::getList(['filter' => [], 'select' => ['ID', 'ACTIVE']]);
        while ($row = $db->fetch()) {
            $id = (int) $row['ID'];
            $active = ($row['ACTIVE'] ?? '') === 'Y';
            if ($id === $keepId) {
                if (!$active) {
                    Manager::update($id, ['ACTIVE' => 'Y']);
                }
                continue;
            }
            if ($active) {
                Manager::update($id, ['ACTIVE' => 'N']);
                ++$count;
            }
        }
        return $count;
    }
}
