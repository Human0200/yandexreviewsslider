<?php

defined('B_PROLOG_INCLUDED') || exit;

use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => [
            'NAME' => Loc::getMessage('LEADSPACE_SETTINGS'),
        ],
        'PARAMS' => [
            'NAME' => Loc::getMessage('LEADSPACE_PARAMETRS'),
        ],
    ],
    'PARAMETERS' => [
        'PAGER_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('LEADSPACE_COUNT_REVIEWS'),
            'TYPE' => 'STRING',
            'DEFAULT' => 10,
        ],
        'NEWS_SORT' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('LEADSPACE_SORT'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'DEFAULT' => Loc::getMessage('LEADSPACE_SORT_DEFAULT'),
                'BY_DATE' => Loc::getMessage('LEADSPACE_SORT_NEW'),
            ],
        ],
        'NO_JQUERY' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('LEADSPACE_NO_JQUERY'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => '',
        ],
        'SLIDE_DESCKTOP_COUNT' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_COUNT_DESCKTOP'),
            'TYPE' => 'STRING',
            'DEFAULT' => 2,
        ],
        'SLIDE_MOBILE_COUNT' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_COUNT_MOBILE'),
            'TYPE' => 'STRING',
            'DEFAULT' => 1,
        ],
        'SHOW_COUNT' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_SHOW_COUNT'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'DEFAULT' => Loc::getMessage('LEADSPACE_NOT_SHOW'),
                'SHOW_COUNT_REVIEWS' => Loc::getMessage('LEADSPACE_SHOW_COUNT_REVIEWS'),
                'SHOW_COUNT_MARKS' => Loc::getMessage('LEADSPACE_SHOW_COUNT_MARKS'),
            ],
        ],
        'AUTOPLAY' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('LEADSPACE_AUTO_SCROLL'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'N' => Loc::getMessage('LEADSPACE_AUTO_SCROLL_N'),
                'Y' => Loc::getMessage('LEADSPACE_AUTO_SCROLL_Y'),
            ],
        ],
        'AUTOPLAY_SPEED' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('LEADSPACE_AUTO_SCROLL_SPEED'),
            'TYPE' => 'STRING',
            'DEFAULT' => 2000,
        ],
        'COLOR_BUTTONS' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_COLOR_BUTTONS'),
            'TYPE' => 'COLORPICKER',
            'DEFAULT' => '#007aff',
        ],
        'COLOR_BUTTON' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_COLOR_BUTTON'),
            'TYPE' => 'COLORPICKER',
            'DEFAULT' => '#ffffff',
        ],
        'COLOR_BUTTON_TEXT' => [
            'PARENT' => 'VISUAL',
            'NAME' => Loc::getMessage('LEADSPACE_COLOR_BUTTON_TEXT'),
            'TYPE' => 'COLORPICKER',
            'DEFAULT' => '#212121',
        ],
        'CACHE_TIME' => [],
    ],
];
