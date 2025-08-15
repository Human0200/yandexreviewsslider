<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory as BitrixDirectory;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\ORM\Entity;
use LeadSpace\YandexReviewsSlider\Model;

final class leadspace_yandexreviewsslider extends CModule
{
    public $arResponse = [
        'STATUS' => true,
        'MESSAGE' => '',
    ];

    private readonly string $COMPONENTS_PATH;

    public function __construct()
    {
        $arModuleVersion = [];

        require __DIR__.'/version.php';

        $this->MODULE_ID = 'leadspace.yandexreviewsslider';

        $this->COMPONENTS_PATH = $_SERVER['DOCUMENT_ROOT'].'/bitrix/components';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('LEADSPACE_REVIEWS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('LEADSPACE_REVIEWS_MODULE_DESCRIPTION');

        $this->PARTNER_NAME = Loc::getMessage('LEADSPACE_REVIEWS_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('LEADSPACE_REVIEWS_PARTNER_URI');

        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = 'Y';
    }

    public function setResponse($status, $message = ''): void
    {
        $this->arResponse['STATUS'] = $status;
        $this->arResponse['MESSAGE'] = $message;
    }

    public function installDB()
    {
        Loader::requireModule($this->MODULE_ID);

        $checkAndCreateTables = [
            Model\ReviewsTable::getEntity(),
            Model\CompanyTable::getEntity(),
        ];

        foreach ($checkAndCreateTables as $tableEntity) {
            if (!$this->isTableExists($tableEntity)) {
                $tableEntity->createDbTable();
            }
        }
    }

    public function installFiles()
    {
        $res = copyDirFiles(
            __DIR__.'/components',
            $this->COMPONENTS_PATH,
            true,
            true
        );

        if (!$res) {
            $resMsg = Loc::getMessage('LEADSPACE_REVIEWS_INSTALL_ERROR_FILES_COM');
            $this->setResponse(false, $resMsg);

            return false;
        }

        $this->setResponse(true);

        return true;
    }

    public function installAgents()
    {
        CAgent::addAgent(
            name: "LeadSpace\YandexReviewsSlider\OverwriteAgent::run();",
            module: $this->MODULE_ID,
            sort: 1
        );
    }

    // Для удобства проверки результата
    public function checkAddResult($result)
    {
        if ($result->isSuccess()) {
            return [true, $result->getId()];
        }

        return [false, $result->getErrorMessages()];
    }

    public function DoInstall()
    {
        global $APPLICATION;

        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        if ($request['step'] < 2) {
            $APPLICATION->includeAdminFile(
                Loc::getMessage('LEADSPACE_REVIEWS_INSTALL_TITLE'),
                __DIR__.'/step1.php'
            );
        } elseif (2 == $request['step']) {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->installDB();
            $this->installAgents();

            if (!$this->installFiles()) {
                $APPLICATION->throwException($this->arResponse['MESSAGE']);
            }
            $APPLICATION->includeAdminFile(
                Loc::getMessage('LEADSPACE_REVIEWS_INSTALL_TITLE'),
                __DIR__.'/step2.php'
            );
        }
    }

    // Удаление файлов
    public function unInstallFiles()
    {
        $res = true;
        $resMsg = '';

        BitrixDirectory::deleteDirectory(
            $this->COMPONENTS_PATH.'/leadspace/yandexreviewsslider'
        );

        if (!$res) {
            $resMsg = Loc::getMessage('LEADSPACE_REVIEWS_UNINSTALL_ERROR_FILES_COM');
        }
        if ($resMsg) {
            $this->setResponse(false, $resMsg);

            return false;
        }
        $this->setResponse(true);

        return true;
    }

    public function unInstallDB()
    {
        Loader::requireModule($this->MODULE_ID);

        $checkAndDropTables = [
            Model\ReviewsTable::getEntity(),
            Model\CompanyTable::getEntity(),
        ];

        foreach ($checkAndDropTables as $tableEntity) {
            $tableEntity->getConnection()->queryExecute(
                'DROP TABLE IF EXISTS '.$tableEntity->getDBTableName()
            );
        }

        Option::delete($this->MODULE_ID);
    }

    public function unInstallAgents()
    {
        CAgent::removeModuleAgents($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        if ($request['step'] < 2) {
            $APPLICATION->includeAdminFile(
                Loc::getMessage('LEADSPACE_REVIEWS_UNINSTALL_TITLE'),
                __DIR__.'/unstep1.php'
            );
        } elseif (2 == $request['step']) {
            $this->unInstallEvents();
            $this->unInstallAgents();

            if ('Y' != $request['save_data']) {
                $this->unInstallDB();
            }

            if (!$this->unInstallFiles()) {
                $APPLICATION->ThrowException($this->arResponse['MESSAGE']);
            }

            ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->includeAdminFile(
                Loc::getMessage('LEADSPACE_REVIEWS_UNINSTALL_TITLE'),
                __DIR__.'/unstep2.php'
            );
        }
    }

    private function isTableExists(Entity $tableEntity)
    {
        return $tableEntity
                  ->getConnection()
                  ->isTableExists($tableEntity->getDBTableName());
    }
}
