<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider\Controller;

use Bitrix\Main\Engine\AutoWire\Parameter;
use Bitrix\Main\Engine\Controller as BitrixController;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use LeadSpace\YandexReviewsSlider\Controller\Prefilter\CheckEditModuleParametersRight;
use LeadSpace\YandexReviewsSlider\Manager;
use LeadSpace\YandexReviewsSlider\Util\ContainerFactory;

final class AdminActions extends BitrixController
{
    public function getDefaultPrefilters()
    {
        $prefilters = parent::getDefaultPreFilters();

        // Префильтр на право редактирования модуля.
        array_push(
            $prefilters,
            new CheckEditModuleParametersRight()
        );

        return $prefilters;
    }

    public function getAutoWiredParameters(): array
    {
        return [
            new Parameter(
                Manager::class,
                function () {
                    return ContainerFactory::getContainer()->get(Manager::class);
                }
            ),
            new Parameter(
                Loc::class,
                function () {
                    return new Loc();
                }
            ),
        ];
    }

    public function importReviewsAction(
        Manager $manager,
        Loc $loc,
    ): ?array {
        try {
            $manager->fullOverwrite();
        } catch (\Throwable $e) {
            $this->addError(
                new Error(
                    $loc->getMessage(
                        'LEADSPACE_YANDEXREVIEWSSLIDER_EXCEPTION_DURING_IMPORT',
                        ['EXCEPTION_MESSAGE' => $e->getMessage()]
                    )
                )
            );

            return null;
        }

        return [
            'message' => $loc->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_SUCCESSFUL_IMPORT'),
        ];
    }
}
