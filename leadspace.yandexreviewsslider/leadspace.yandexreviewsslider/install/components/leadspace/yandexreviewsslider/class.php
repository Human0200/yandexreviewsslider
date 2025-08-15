<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use LeadSpace\YandexReviewsSlider\Manager;
use LeadSpace\YandexReviewsSlider\Util\ContainerFactory;

final class ReviewsSlider extends CBitrixComponent
{
    public const DEFAULT_DISPLAYED_REVIEWS_NUMBER = 5;

    public function executeComponent()
    {
        if (!$this->checkRequiredModules()) {
            $this->includeComponentTemplate();

            return;
        }

        // Если кэширование не начинается (т.е. есть валидный кэш на диске)
        // отдать кэш и прервать выполнение компонента. Иначе, продолжить
        // кэширование и завершить его после подключения шаблона.
        //
        // Параметры кеширования не указываются, т.к. кеш компонентов,
        // обычно, обновляется раз в час. В то время как агент
        // модуля обновляет отзывы примерно раз в сутки.
        if (!$this->startResultCache()) {
            return;
        }

        $container = ContainerFactory::getContainer();

        $manager = $container->get(Manager::class);

        $numberOfDisplayedReviews = intval($this->arParams['PAGER_COUNT']) ?: self::DEFAULT_DISPLAYED_REVIEWS_NUMBER;

        if ('BY_DATE' === $this->arParams['NEWS_SORT']) {
            $reviewsForComponent = $manager->getReviewsForComponent(
                $numberOfDisplayedReviews,
                'DATA',
                'DESC'
            );
        } else {
            $reviewsForComponent = $manager->getReviewsForComponent(
                $numberOfDisplayedReviews
            );
        }

        $this->arResult['COMPANY_FOR_COMPONENT'] = $manager->getCompanyInfoForComponent();
        $this->arResult['REVIEWS_FOR_COMPONENT'] = $reviewsForComponent;
        $this->arResult['COMPANY_REVIEWS_PAGE_URL'] = $manager->getCompanyReviewsPageUrl();
        $this->arResult['URL_FOR_LEAVE_REVIEW'] = $manager->getUrlForAddReview();
        $this->arResult['HIDE_LOGO'] = $manager->requiredToHideLogoInComponent();

        $this->includeComponentTemplate();
    }

    private function checkRequiredModules(): bool
    {
        try {
            Loader::requireModule('leadspace.yandexreviewsslider');

            return true;
        } catch (LoaderException $exception) {
            $this->arResult['EXCEPTION_OBJECT'] = $exception;

            return false;
        }
    }
}
