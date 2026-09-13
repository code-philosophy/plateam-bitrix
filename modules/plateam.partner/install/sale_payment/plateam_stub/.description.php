<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$data = [
    'NAME' => [
        'ru' => 'PLATEAM тест (стенд)',
        'en' => 'PLATEAM test (staging)',
    ],
    'SORT' => 100,
    'CODES' => [],
];

$description = [
    'MAIN' => [
        'ru' => 'Имитация оплаты через банк для staging. Деньги не списываются.',
        'en' => 'Staging bank payment simulation. No real charges.',
    ],
];
