<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider\Controller\Prefilter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;

final class CheckEditModuleParametersRight extends Base
{
    public function onBeforeAction(Event $event)
    {
        global $APPLICATION; // :(

        $userHasEditRight = $APPLICATION->getGroupRight(
            $event->getModuleId()
        ) > 'R';

        if (!$userHasEditRight) {
            $this->addError(
                new Error(
                    Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_NO_RIGHTS_TO_EDIT_MODULE_PARAMETERS')
                )
            );

            return new EventResult(
                EventResult::ERROR,
                null,
                null,
                $this
            );
        }

        return null;
    }
}
