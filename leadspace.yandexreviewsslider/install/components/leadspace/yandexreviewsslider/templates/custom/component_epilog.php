<?php

/**
 * Эпилог для подключения некешируемых ассетов на каждом хите.
 */
defined('B_PROLOG_INCLUDED') || exit;

if ('Y' != $arParams['NO_JQUERY']) {
    CJSCore::Init(['jquery']);
}

$asset = Bitrix\Main\Page\Asset::getInstance();
$asset->addCss($templateFolder.'/slick/slick.css');
$asset->addCss($templateFolder.'/slick/slick-theme.css');
$asset->addJs($templateFolder.'/slick/slick.min.js');
$asset->addJs($templateFolder.'/js/readmore.js');
