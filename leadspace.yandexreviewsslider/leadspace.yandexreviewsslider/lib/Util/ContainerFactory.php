<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider\Util;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use DI\Container;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContainerFactory
{
    public static function getContainer(): ContainerInterface
    {
        return new Container(self::getContainerConfig());
    }

    private static function getContainerConfig(): array
    {
        return [
            HttpClientInterface::class => fn () => HttpClient::create(),
            Application::class => fn () => Application::getInstance(),
            Connection::class => function (ContainerInterface $c) {
                return $c->get(Application::class)::getConnection();
            },
        ];
    }
}
