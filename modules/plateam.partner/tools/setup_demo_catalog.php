<?php

/**
 * Пилот east: оставить 3 товара по 10 000 / 50 000 / 100 000 ₽.
 * Запуск: /local/modules/plateam.partner/tools/setup_demo_catalog.php
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Catalog\Model\Price;
use Bitrix\Catalog\ProductTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    echo json_encode(['ok' => false, 'error' => 'iblock/catalog module missing'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    die();
}

Loader::includeModule('currency');

$imagesDir = realpath(__DIR__ . '/../install/demo_images') ?: '';

$catalogProducts = [
    [
        'xmlId' => 'PLATEAM_DEMO_10000',
        'code' => 'plateam-demo-10000',
        'name' => 'Товар 10 000 ₽',
        'price' => 10000.0,
        'image' => 'plateam-demo-10000.png',
    ],
    [
        'xmlId' => 'PLATEAM_DEMO_50000',
        'code' => 'plateam-demo-50000',
        'name' => 'Товар 50 000 ₽',
        'price' => 50000.0,
        'image' => 'plateam-demo-50000.png',
    ],
    [
        'xmlId' => 'PLATEAM_DEMO_100000',
        'code' => 'plateam-demo-100000',
        'name' => 'Товар 100 000 ₽',
        'price' => 100000.0,
        'image' => 'plateam-demo-100000.png',
    ],
];

/**
 * @return array{attached:bool,fileId:int|null,path:string|null}
 */
function plateamAttachProductImage(int $productId, int $iblockId, string $imagesDir, string $fileName): array
{
    $path = $imagesDir !== '' ? $imagesDir . DIRECTORY_SEPARATOR . $fileName : '';
    if ($path === '' || !is_file($path)) {
        return ['attached' => false, 'fileId' => null, 'path' => $path ?: null];
    }

    $preview = CFile::MakeFileArray($path);
    $detail = CFile::MakeFileArray($path);
    $gallery = CFile::MakeFileArray($path);
    if (!$preview || empty($preview['tmp_name'])) {
        return ['attached' => false, 'fileId' => null, 'path' => $path];
    }

    $element = new CIBlockElement();
    if (!$element->Update($productId, [
        'PREVIEW_PICTURE' => $preview,
        'DETAIL_PICTURE' => $detail,
    ])) {
        return ['attached' => false, 'fileId' => null, 'path' => $path];
    }

    CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [
        'MORE_PHOTO' => $gallery,
    ]);

    $row = CIBlockElement::GetByID($productId)->GetNext();

    return [
        'attached' => true,
        'fileId' => isset($row['PREVIEW_PICTURE']) ? (int) $row['PREVIEW_PICTURE'] : null,
        'path' => $path,
    ];
}

$iblockId = 0;
$iblockRes = CIBlock::GetList(['ID' => 'ASC'], ['TYPE' => 'catalog', 'ACTIVE' => 'Y']);
while ($row = $iblockRes->Fetch()) {
    $cntRes = CIBlockElement::GetList([], ['IBLOCK_ID' => (int) $row['ID'], 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID']);
    if ($cntRes->SelectedRowsCount() > 0) {
        $iblockId = (int) $row['ID'];
        break;
    }
}
if ($iblockId <= 0) {
    $fallback = CIBlock::GetList(['ID' => 'ASC'], ['TYPE' => 'catalog', 'ACTIVE' => 'Y'])->Fetch();
    $iblockId = $fallback ? (int) $fallback['ID'] : 0;
}

if ($iblockId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'catalog iblock not found'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    die();
}

$sectionId = 0;
$sectionRes = CIBlockSection::GetList(
    [],
    ['IBLOCK_ID' => $iblockId, 'CODE' => 'plateam-pilot', 'CHECK_PERMISSIONS' => 'N'],
    false,
    ['ID']
);
if ($sec = $sectionRes->Fetch()) {
    $sectionId = (int) $sec['ID'];
} else {
    $section = new CIBlockSection();
    $sectionId = (int) $section->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => 'PLATEAM пилот',
        'CODE' => 'plateam-pilot',
        'ACTIVE' => 'Y',
        'SORT' => 10,
    ]);
    if ($sectionId <= 0) {
        echo json_encode([
            'ok' => false,
            'error' => 'section create failed',
            'detail' => $section->LAST_ERROR,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        die();
    }
}

$baseGroupId = 1;
$groupRes = CCatalogGroup::GetList(['SORT' => 'ASC'], ['BASE' => 'Y']);
if ($group = $groupRes->Fetch()) {
    $baseGroupId = (int) $group['ID'];
}

$elementApi = new CIBlockElement();
$keptIds = [];
$report = ['created' => [], 'updated' => [], 'deactivated' => [], 'deactivatedSections' => [], 'images' => []];

foreach ($catalogProducts as $spec) {
    $existingId = 0;
    $existingRes = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $iblockId,
            'XML_ID' => $spec['xmlId'],
            'CHECK_PERMISSIONS' => 'N',
        ],
        false,
        false,
        ['ID']
    );
    if ($existing = $existingRes->Fetch()) {
        $existingId = (int) $existing['ID'];
    }

    $fields = [
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $sectionId,
        'NAME' => $spec['name'],
        'CODE' => $spec['code'],
        'XML_ID' => $spec['xmlId'],
        'ACTIVE' => 'Y',
        'SORT' => 100,
    ];

    if ($existingId > 0) {
        if (!$elementApi->Update($existingId, $fields)) {
            echo json_encode([
                'ok' => false,
                'error' => 'element update failed',
                'xmlId' => $spec['xmlId'],
                'detail' => $elementApi->LAST_ERROR,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            die();
        }
        $productId = $existingId;
        $report['updated'][] = $productId;
    } else {
        $productId = (int) $elementApi->Add($fields);
        if ($productId <= 0) {
            echo json_encode([
                'ok' => false,
                'error' => 'element create failed',
                'xmlId' => $spec['xmlId'],
                'detail' => $elementApi->LAST_ERROR,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            die();
        }
        $report['created'][] = $productId;
    }

    $keptIds[] = $productId;

    $catalogRow = ProductTable::getRowById($productId);
    if (!$catalogRow) {
        CCatalogProduct::Add([
            'ID' => $productId,
            'QUANTITY' => 999,
            'QUANTITY_TRACE' => 'N',
            'CAN_BUY_ZERO' => 'Y',
            'TYPE' => CCatalogProduct::TYPE_PRODUCT,
        ]);
    } else {
        ProductTable::update($productId, [
            'QUANTITY' => 999,
            'QUANTITY_TRACE' => 'N',
            'CAN_BUY_ZERO' => 'Y',
        ]);
    }

    $priceRow = Price::getList([
        'filter' => [
            'PRODUCT_ID' => $productId,
            'CATALOG_GROUP_ID' => $baseGroupId,
        ],
        'select' => ['ID'],
    ])->fetch();

    $priceFields = [
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $baseGroupId,
        'PRICE' => $spec['price'],
        'CURRENCY' => 'RUB',
        'PRICE_SCALE' => $spec['price'],
    ];

    if ($priceRow) {
        Price::update((int) $priceRow['ID'], $priceFields);
    } else {
        Price::add($priceFields);
    }

    if (!empty($spec['image'])) {
        $report['images'][$spec['xmlId']] = plateamAttachProductImage(
            $productId,
            $iblockId,
            $imagesDir,
            (string) $spec['image'],
        );
    }
}

$allRes = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
    false,
    false,
    ['ID', 'XML_ID', 'NAME', 'ACTIVE']
);

while ($item = $allRes->Fetch()) {
    $id = (int) $item['ID'];
    if (in_array($id, $keptIds, true)) {
        continue;
    }
    if ($item['ACTIVE'] === 'N') {
        continue;
    }
    if ($elementApi->Update($id, ['ACTIVE' => 'N'])) {
        $report['deactivated'][] = [
            'id' => $id,
            'name' => $item['NAME'],
        ];
    }
}

$sectionApi = new CIBlockSection();
$sectionsRes = CIBlockSection::GetList(
    ['LEFT_MARGIN' => 'ASC'],
    ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
    false,
    ['ID', 'NAME', 'CODE', 'ACTIVE']
);
while ($sec = $sectionsRes->Fetch()) {
    $secId = (int) $sec['ID'];
    if ($secId === $sectionId) {
        continue;
    }
    if ($sec['ACTIVE'] === 'N') {
        continue;
    }
    if ($sectionApi->Update($secId, ['ACTIVE' => 'N'])) {
        $report['deactivatedSections'][] = [
            'id' => $secId,
            'name' => $sec['NAME'],
        ];
    }
}

if (class_exists('\Bitrix\Iblock\PropertyIndex\Manager')) {
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($iblockId, $keptIds[0] ?? 0);
}

echo json_encode([
    'ok' => true,
    'iblockId' => $iblockId,
    'sectionId' => $sectionId,
    'sectionUrl' => CIBlock::ReplaceDetailUrl(
        CIBlock::GetArrayByID($iblockId, 'SECTION_PAGE_URL'),
        ['SECTION_ID' => $sectionId, 'IBLOCK_ID' => $iblockId],
        false,
        'S'
    ),
    'products' => array_map(static function ($spec, $idx) use ($keptIds, $report) {
        return [
            'id' => $keptIds[$idx] ?? null,
            'name' => $spec['name'],
            'price' => $spec['price'],
            'xmlId' => $spec['xmlId'],
            'image' => $report['images'][$spec['xmlId']] ?? null,
        ];
    }, $catalogProducts, array_keys($catalogProducts)),
    'report' => $report,
    'at' => (new DateTime())->format('c'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
