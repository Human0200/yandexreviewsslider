<?php

use Bitrix\Main\Localization\Loc;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

if (!$APPLICATION->GetGroupRight('leadspace.yandexreviewsslider') > 'D') {
    return false;
}

return [
    'parent_menu' => 'global_menu_services',
    'sort' => 100,
    'url' => '/bitrix/admin/settings.php?lang=ru&mid=leadspace.yandexreviewsslider&mid_menu=1&lang='.LANGUAGE_ID,
    'more_url' => '',
    'text' => Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_OTZYVY_S_ANDEKS_KART'),
    'title' => Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_OTZYVY_S_ANDEKS_KART'),
    'icon' => 'form_menu_icon',
    'page_icon' => 'form_page_icon',
    'module_id' => 'leadspace.yandexreviewsslider',
    'dynamic' => false,
    'items_id' => 'leadspace.yandexreviewsslider',
    'items' => [],
];
