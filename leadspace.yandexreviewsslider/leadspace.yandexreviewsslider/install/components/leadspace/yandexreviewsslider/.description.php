<?php

defined('B_PROLOG_INCLUDED') || exit;

use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_OTZYVY_S_ANDEKS_KART'),
    'ID' => 'leadspace',
    'DESCRIPTION' => Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_OTZYVY_S_ANDEKS_KART'),
    'ICON' => '',
    'SORT' => 1,
    'PATH' => [
        'ID' => 'leadspace',
        'CHILD' => [
            'ID' => 'leadspace',
            'NAME' => Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_OTZYVY_S_ANDEKS_KART'),
        ],
    ],
];
