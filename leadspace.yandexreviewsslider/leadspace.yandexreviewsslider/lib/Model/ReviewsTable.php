<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider\Model;

use Bitrix\Main\Entity;

class ReviewsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'leadspace_yandexreviewsslider';
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

            new Entity\StringField('NAME'),
            new Entity\StringField('IMAGE'),
            new Entity\IntegerField('RATING'),
            new Entity\IntegerField('DATA'),
            new Entity\StringField('TIME'),
            new Entity\TextField('DESCRIPTION'),
        ];
    }
}
