<?php

defined('B_PROLOG_INCLUDED') || exit;

use Bitrix\Main\Localization\Loc;

// Отобразить ошибку,если она есть.
if (array_key_exists('EXCEPTION_OBJECT', $arResult)) {
    showError(
        Loc::getMessage(
            'LEADSPACE_YANDEXREVIEWSSLIDER_MODULE_INCLUDE_ERROR',
            [
                'ERROR_MESSAGE' => $arResult['EXCEPTION_OBJECT']->getMessage(),
            ]
        )
    );

    // Прервать отрисовку шаблона.
    return;
}

if (empty($arParams['SLIDE_DESCKTOP_COUNT'])) {
    $arParams['SLIDE_DESCKTOP_COUNT'] = 2;
}
if (empty($arParams['SLIDE_MOBILE_COUNT'])) {
    $arParams['SLIDE_MOBILE_COUNT'] = 1;
}
if (empty($arParams['AUTOPLAY_SPEED'])) {
    $arParams['AUTOPLAY_SPEED'] = 2000;
}

?>

<style>
    .lsyr_review-bottom .lsyr_button-next:hover, .lsyr_review-bottom .lsyr_button-prev:hover {
        background-color: <?php echo $arParams['COLOR_BUTTONS'] ? $arParams['COLOR_BUTTONS'] : '#007aff'; ?> !important;
        color: #ffffff !important;
    }
    .slick-dots li.slick-active button {
        background: <?php echo $arParams['COLOR_BUTTONS'] ? $arParams['COLOR_BUTTONS'] : '#007aff'; ?>;
    }
    .lsyr_app-body a.lsyr_reviews-btn {
        color: <?php echo $arParams['COLOR_BUTTON_TEXT'] ? $arParams['COLOR_BUTTON_TEXT'] : '#212121'; ?> !important;
        background-color: <?php echo $arParams['COLOR_BUTTON'] ? $arParams['COLOR_BUTTON'] : '#ffffff'; ?>;
    }
</style>
<div class="lsyr_app-body ">
    <div class="lsyr_review-box lsyr_review-unselectable">
        <div class="lsyr_business-summary-rating-badge-view__rating"><?php // =$arResult["COMPANY"][0]["NAME"]?>    <?php echo $arResult['COMPANY_FOR_COMPONENT']->getRating(); ?> <?php echo Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_IZ'); ?></div>
        <div class="lsyr_business-rating-badge-view__stars">
            <?php
            $fullStarsCount = $arResult['COMPANY_FOR_COMPONENT']->getFullStars();
if ($fullStarsCount > 0) {
    for ($n = 1; $n <= $fullStarsCount; ++$n) {
        echo '<span class="inline-image _loaded icon lsyr_business-rating-badge-view__star _full" aria-hidden="true" role="button" tabindex="-1" style="font-size: 0px; line-height: 0;"><svg width="22" height="22" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.985 11.65l-3.707 2.265a.546.546 0 0 1-.814-.598l1.075-4.282L1.42 6.609a.546.546 0 0 1 .29-.976l4.08-.336 1.7-3.966a.546.546 0 0 1 1.004.001l1.687 3.965 4.107.337c.496.04.684.67.29.976l-3.131 2.425 1.073 4.285a.546.546 0 0 1-.814.598L7.985 11.65z" fill="currentColor"></path></svg></span>';
    }
}

$halfStarsCount = $arResult['COMPANY_FOR_COMPONENT']->getHalfStars();
if ($halfStarsCount) {
    for ($n = 1; $n <= $halfStarsCount; ++$n) {
        echo '<span class="inline-image _loaded icon lsyr_business-rating-badge-view__star _half" aria-hidden="true" role="button" tabindex="-1" style="font-size: 0px; line-height: 0;"><svg width="22" height="22" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.985 11.65l-3.707 2.266a.546.546 0 0 1-.814-.598l1.075-4.282L1.42 6.609a.546.546 0 0 1 .29-.975l4.08-.336 1.7-3.966a.546.546 0 0 1 1.004.001l1.687 3.965 4.107.337c.496.04.684.67.29.975l-3.131 2.426 1.073 4.284a.546.546 0 0 1-.814.6l-3.722-2.27z" fill="#CCC"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M4.278 13.915l3.707-2.266V1a.538.538 0 0 0-.494.33l-1.7 3.967-4.08.336a.546.546 0 0 0-.29.975l3.118 2.427-1.075 4.282a.546.546 0 0 0 .814.598z" fill="#FC0"></path></svg></span>';
    }
}

$emptyStarsCount = $arResult['COMPANY_FOR_COMPONENT']->getEmptyStars();
if ($emptyStarsCount > 0) {
    for ($n = 1; $n <= $emptyStarsCount; ++$n) {
        echo '<span class="inline-image _loaded icon lsyr_business-rating-badge-view__star _empty" aria-hidden="true" role="button" tabindex="-1" style="font-size: 0px; line-height: 0;"><svg width="22" height="22" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.985 11.65l-3.707 2.265a.546.546 0 0 1-.814-.598l1.075-4.282L1.42 6.609a.546.546 0 0 1 .29-.976l4.08-.336 1.7-3.966a.546.546 0 0 1 1.004.001l1.687 3.965 4.107.337c.496.04.684.67.29.976l-3.131 2.425 1.073 4.285a.546.546 0 0 1-.814.598L7.985 11.65z" fill="currentColor"></path></svg></span>';
    }
}?>
          </div>
        <a target="_blank" href="<?php echo $arResult['COMPANY_REVIEWS_PAGE_URL']; ?>">
        <img src="<?php echo $templateFolder; ?>/review_yandex_map.svg" style="width: 80px;"></a>
        <?php if ('SHOW_COUNT_REVIEWS' == $arParams['SHOW_COUNT']) {?>
            <div class="lsyr_reviews-count"><a target="_blank"
                                             href="<?php echo $arResult['COMPANY_REVIEWS_PAGE_URL']; ?>"><?php echo Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_NA'); ?><?php echo $arResult['COMPANY_FOR_COMPONENT']->getCountreviews(); ?></a>
            </div>
        <?php }?>

        <?php if ('SHOW_COUNT_MARKS' == $arParams['SHOW_COUNT']) { ?>
            <div class="lsyr_reviews-count"><a target="_blank"
                                             href="<?php echo $arResult['URL_FOR_LEAVE_REVIEW']; ?>"><?php echo Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_NA'); ?><?php echo $arResult['COMPANY_FOR_COMPONENT']->getCountmarks(); ?></a>
            </div>
        <?php } ?>

        <a target="_blank" href="<?php echo $arResult['URL_FOR_LEAVE_REVIEW']; ?>"
           class="lsyr_reviews-btn lsyr_reviews-btn-form"
           rel="nofollow noreferrer noopener"><?php echo Loc::getMessage('LEADSPACE_LEAVE_FEEDBACK'); ?></a>
    </div>
    <div style="min-width: 0;width: 100%;">
        <div class="lsyr_review-list">
            <div class="lsyr_regular">
                <?php foreach ($arResult['REVIEWS_FOR_COMPONENT'] as $review) { ?>
                    <div class="lsyr_slide">
                        <div class="lsyr_review-item">

                            <div class="lsyr_business-review-view__info">
                                <div class="lsyr_business-review-view__author-container">
                                    <div class="lsyr_business-review-view__author-image">
                                        <div class="lsyr_user-icon-view__icon" style="background-image:url(<?php echo $review->getImage(); ?>)"></div>
                                    </div>
                                    <div class="lsyr_business-review-view__author-info">
                                        <div class="ls_business-review-view__author-name"><?php echo $review->getName(); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="lsyr_business-review-view__header">
                                    <div class="lsyr_business-review-view__rating">
                                        <div class="lsyr_business-rating-badge-view _size_m _weight_medium">
                                            <div class="lsyr_business-rating-badge-view__stars">
                                                <?php for ($n = 1; $n <= 5; ++$n) {
                                                    if ($n <= $review->getRating()) {
                                                        echo '<span class="inline-image _loaded icon lsyr_business-rating-badge-view__star _full" aria-hidden="true" role="button" tabindex="-1" style="font-size: 0px; line-height: 0;"><svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.985 11.65l-3.707 2.265a.546.546 0 0 1-.814-.598l1.075-4.282L1.42 6.609a.546.546 0 0 1 .29-.976l4.08-.336 1.7-3.966a.546.546 0 0 1 1.004.001l1.687 3.965 4.107.337c.496.04.684.67.29.976l-3.131 2.425 1.073 4.285a.546.546 0 0 1-.814.598L7.985 11.65z" fill="currentColor"></path></svg></span>';
                                                    } else {
                                                        echo '<span class="inline-image _loaded icon lsyr_business-rating-badge-view__star _empty" aria-hidden="true" role="button" tabindex="-1" style="font-size: 0px; line-height: 0;"><svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.985 11.65l-3.707 2.265a.546.546 0 0 1-.814-.598l1.075-4.282L1.42 6.609a.546.546 0 0 1 .29-.976l4.08-.336 1.7-3.966a.546.546 0 0 1 1.004.001l1.687 3.965 4.107.337c.496.04.684.67.29.976l-3.131 2.425 1.073 4.285a.546.546 0 0 1-.814.598L7.985 11.65z" fill="currentColor"></path></svg></span>';
                                                    }
                                                } ?>

                                            </div>
                                        </div>
                                    </div>
                                    <span class="lsyr_business-review-view__date"> <?php echo $review->getTime(); ?></span>
                                </div>
                                <div dir="auto" class="lsyr_business-review-view__body">
                                    <div class="lsyr_items-text">
                                        <?php echo $review->getDescription(); ?>
                                    </div>
                                    <a target="_blank" href="<?php echo $arResult['COMPANY_REVIEWS_PAGE_URL']; ?>"
                                       class="lsyr_review-source-link"><?php echo Loc::getMessage('LEADSPACE_YANDEX_MAP_REVIEW'); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
            </div>
        </div>
        <div class="lsyr_review-bottom">
            <div class="lsyr_buttons">
                <div class="lsyr_button-prev" tabindex="0" role="button" aria-label="Previous slide">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="height: 12px">
                        <path d="m70.143 97.5-44.71-44.711a3.943 3.943 0 0 1 0-5.578l44.71-44.711 5.579 5.579-41.922 41.921 41.922 41.922z"/>
                    </svg>
                </div>
                <div class="lsyr_button-next" tabindex="0" role="button" aria-label="Next slide">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"
                         style="height: 12px;">
                        <path d="m70.143 97.5-44.71-44.711a3.943 3.943 0 0 1 0-5.578l44.71-44.711 5.579 5.579-41.922 41.921 41.922 41.922z"/>
                    </svg>
                </div>
            </div>
            <div class="lsyr_pagination lsyr_pagination-clickable lsyr_pagination-bullets lsyr_pagination-horizontal">
            </div>
            <?php if (!$arResult['HIDE_LOGO']) { ?>
                <div class="lsyr_business-review-view_copy">
                <div><?php echo Loc::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_RAZRABOTANO'); ?></div>
                <a href="https://lead-space.ru/?utm_source=marketplace.1c-bitrix" target="_blank"><img
                            src="<?php echo $templateFolder; ?>/logo.svg" style="width:70px"></a></div><?php } ?>
        </div>
    </div>
</div>

<script>
    $('.lsyr_items-text').readmore({
        collapsedHeight: 40
    });
    $(".lsyr_regular").slick({
        dots: true,
        infinite: true,
        slidesToShow: <?php echo $arParams['SLIDE_DESCKTOP_COUNT']; ?>,
        prevArrow: $('.lsyr_button-prev'),
        nextArrow: $('.lsyr_button-next'),
        appendDots: $('.lsyr_pagination'),
        responsive: [

            {
                breakpoint: 800,
                settings: {
                    slidesToShow: <?php echo $arParams['SLIDE_MOBILE_COUNT']; ?>,
                    dots: false,
                    vertical: true,
                    verticalSwiping: true,
                }
            }

        ],
        <?php if ('Y' == $arParams['AUTOPLAY']) { ?>
        autoplay: true,
        autoplaySpeed: <?php echo $arParams['AUTOPLAY_SPEED']; ?>,
        <?php } ?>
    });
</script>
