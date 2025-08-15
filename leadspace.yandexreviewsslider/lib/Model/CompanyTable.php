<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider\Model;

use Bitrix\Main\Entity;

class CompanyTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'leadspace_reviews_company';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField(
                'ID',
                [
                    'primary' => true,
                    'autocomplete' => true,
                ]
            ),
            new Entity\StringField(
                'NAME',
                [
                    'required' => true,
                ]
            ),
            new Entity\StringField('RATING'),
            new Entity\StringField('FULL_STARS'),
            new Entity\IntegerField('HALF_STARS'),
            new Entity\IntegerField('EMPTY_STARS'),
            new Entity\StringField('COUNTREVIEWS'),
            new Entity\StringField('COUNTMARKS'),
        ];
    }
}
