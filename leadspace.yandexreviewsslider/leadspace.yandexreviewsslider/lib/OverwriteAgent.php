<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider;

use Bitrix\Main\Type\DateTime as BitrixDateTime;

final class OverwriteAgent
{
    public static function run(): string
    {
        try {
            \CAgent::AddAgent(
                name: __CLASS__.'::oneTimeOverwrite();',
                module: 'leadspace.yandexreviewsslider',
                period: 'N',
                next_exec: self::getRandomTimeForOneTimeAgent()
            );
        } catch (\Throwable $e) {
            // Поймать любое исключение чтобы агент сохранился.
        }

        return __CLASS__.'::'.__FUNCTION__.'();';
    }

    public static function oneTimeOverwrite(): string
    {
        try {
            $container = Util\ContainerFactory::getContainer();
            $manager = $container->get(Manager::class);
            $manager->fullOverwrite();
        } catch (\Throwable $e) {
            // Поймать любое исключение для корректного завершения агента.
        }

        // Вернуть пустую строку чтобы агент удалился.
        return '';
    }

    private static function getRandomTimeForOneTimeAgent(): string
    {
        $shift = \random_int(3600, 43200);

        return BitrixDateTime::createFromTimestamp(time() + $shift)->format('d.m.Y H:i:s');
    }
}
