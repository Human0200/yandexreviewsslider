<?php

use Bitrix\Main\IO\Directory;
use Bitrix\Main\ModuleManager;

// Прервать скрипт, если корректный updater не обнаружен или модуль не установлен.
/** @var CUpdater $updater */
if (!isset($updater) || !$updater instanceof CUpdater || !ModuleManager::isModuleInstalled($updater->moduleID)) {
    return;
}

$updater->installComponents();

// Удалить файлы, некорректно установленные в local/ (предыдущими обновлениями).
$dirsToDelete = [
    $_SERVER['DOCUMENT_ROOT'].'/local/leadspace/yandexreviewsslider',
    $_SERVER['DOCUMENT_ROOT'].'/local/components/leadspace/yandexreviewsslider',
];

foreach ($dirsToDelete as $dirToDelete) {
    Directory::deleteDirectory($dirToDelete);
}

CAgent::removeModuleAgents($updater->moduleID);

CAgent::addAgent(
    name: "LeadSpace\YandexReviewsSlider\OverwriteAgent::run();",
    module: $updater->moduleID,
    sort: 1
);
