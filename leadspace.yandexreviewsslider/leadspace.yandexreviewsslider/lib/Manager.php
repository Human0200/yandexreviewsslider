<?php

declare(strict_types=1);

namespace LeadSpace\YandexReviewsSlider;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option as BitrixModuleOptions;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json as BitrixJson;
use LeadSpace\YandexReviewsSlider\Exception\ParsingInvalidBody;
use LeadSpace\YandexReviewsSlider\Exception\ParsingRequestTimeout;
use LeadSpace\YandexReviewsSlider\Exception\ParsingUnexpectedStatusCode;
use LeadSpace\YandexReviewsSlider\Exception\RequiredParametersMissing;
use LeadSpace\YandexReviewsSlider\Model\CompanyTable;
use LeadSpace\YandexReviewsSlider\Model\EO_Company;
use LeadSpace\YandexReviewsSlider\Model\EO_Reviews_Collection;
use LeadSpace\YandexReviewsSlider\Model\ReviewsTable;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * God Object класс, выполняющий основную логику модуля.
 */
final class Manager
{
    /**
     * Base URL для запроса отзывов о компании с Яндекс Карт.
     */
    private const COMPANY_YANDEX_MAPS_REVIEWS_PAGE_BASE_URL = 'https://yandex.ru/maps/org';

    /**
     * Base URL для получения отзывов об организации.
     */
    private const REVIEWS_LIST_BASE_URL = 'https://yandex.ru/ugcpub/digest';

    /**
     * Base URL для получения аватара пользователя с CDN Яндекса.
     */
    private const USER_AVATAR_BASE_URL = 'https://avatars.mds.yandex.net/get-yapic';

    /**
     * Необходимый размер аватара пользователя для получения с CDN Яндекса.
     */
    private const USER_AVATAR_SIZE = 'islands-68';

    /**
     * Количество получаемых отзывов с Яндекса.
     */
    private const NUMBER_OF_REVIEWS_RECEIVED = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BitrixModuleOptions $bitrixModuleOptions,
        private readonly ReviewsTable $reviewsTable,
        private readonly CompanyTable $companyTable,
        private readonly BitrixJson $bitrixJson,
        private readonly Connection $connection,
        private readonly Loc $localization,
    ) {
    }

    /**
     * Полностью перезаписывает информацию о компании и отзывы.
     */
    public function fullOverwrite(): void
    {
        $this->checkRequiredModuleParams();
        $this->fullCompanyInfoOverwrite();
        $this->fullReviewsOverwrite();
    }

    /**
     * Возвращает коллекцию отзывов для отображения в компоненте.
     *
     * @return EO_Reviews_Collection<EO_Reviews> объект виртуального класса коллекции объектов,
     *                                           позвляющих получить информацию о каждом отзыве
     */
    public function getReviewsForComponent(
        int $limit,
        ?string $sortColumn = null,
        ?string $sortOrder = null,
    ): EO_Reviews_Collection {
        $queryBuilder = $this->reviewsTable::query()
                                        ->setSelect(['*'])
                                        ->setLimit($limit);

        $hideNegativeReviews = 'Y' === $this->bitrixModuleOptions->get(
            'leadspace.yandexreviewsslider',
            'hide_negative'
        );

        if ($hideNegativeReviews) {
            $queryBuilder->setFilter([
                '@RATING' => [4, 5],
            ]);
        }

        if (!is_null($sortColumn) && !is_null($sortOrder)) {
            $queryBuilder->setOrder([$sortColumn => $sortOrder]);
        }

        return $queryBuilder
                 ->exec()
                 ->fetchCollection();
    }

    /**
     * Возвращает информацию о компании.
     *
     * @return EO_Company Объект виртуального класса позволяющий получить информацию о компании
     */
    public function getCompanyInfoForComponent(): EO_Company
    {
        return $this->companyTable::getList()->fetchObject();
    }

    /**
     * Возвращает URL страницы отзывов на Яндекс Картах.
     */
    public function getCompanyReviewsPageUrl(): string
    {
        $companyId = $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'company_id');

        return self::COMPANY_YANDEX_MAPS_REVIEWS_PAGE_BASE_URL.'/'.$companyId.'/reviews/';
    }

    /**
     * Возвращает URL, по которому можно оставить отзыв о компании на Яндекс Картах.
     */
    public function getUrlForAddReview(): string
    {
        return $this->getCompanyReviewsPageUrl().'?add-review=true';
    }

    /**
     * Возвращает значение параметра "Скрыть логотип разработчика".
     */
    public function requiredToHideLogoInComponent(): bool
    {
        return 'Y' === $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'hide_logo');
    }

    /**
     * Проверяет заполненность обязательных параметров
     * модуля перед перезаписью информации о компании и отзывов.
     */
    private function checkRequiredModuleParams(): void
    {
        $companyId = $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'company_id');

        if (0 === intval($companyId)) {
            throw new RequiredParametersMissing($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_MISSING_REQUIRED_PARAMETERS'));
        }
    }

    /**
     * Полностью перезаписывает информацию о компании.
     *
     * Метод:
     * - Запрашивает актуальную информацию о компании.
     * - Проверяет ответ на запрос на наличие необходимых данных.
     * - Перезаписывает информацию о компании в БД.
     */
    private function fullCompanyInfoOverwrite()
    {
        $latestCompanyInfoResponse = $this->requestLatestCompanyInfo();

        $this->checkCompanyInfoResponse($latestCompanyInfoResponse);

        $this->overwriteCompanyInfo($latestCompanyInfoResponse);
    }

    /**
     * Осуществляет HTTP запрос, перехватывая исключение в случае тайматуа и выбрасывает собственное.
     *
     * @return ResponseInterface ответ на HTTP запрос
     *
     * @throws ParsingRequestTimeout произошёл таймаут
     */
    private function makeRequestWithHandleTimeout(string $method, string $url, array $options = []): ResponseInterface
    {
        try {
            return $this->httpClient->request(
                $method,
                $url,
                $options
            );
        } catch (TimeoutException) {
            throw new ParsingRequestTimeout($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_REQUEST_TIMEOUT'));
        }
    }

    /**
     * Запрашивает информацию о компании с Яндекс Карт.
     *
     * @return ResponseInterface результат запроса
     */
    private function requestLatestCompanyInfo(): ResponseInterface
    {
        $companyId = $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'company_id');

        return $this->makeRequestWithHandleTimeout(
            'GET',
            self::COMPANY_YANDEX_MAPS_REVIEWS_PAGE_BASE_URL.'/'.$companyId.'/reviews/',
            [
                'headers' => $this->generateRandomHeaders(),
            ]
        );
    }

    /**
     * Проверяет, не вернулся ли 4xx или 5xx статус-код при выполнении запроса.
     *
     * @param ResponseInterface $response ответ на запрос
     *
     * @throws ParsingUnexpectedStatusCode если статус-код равен 4xx или 5xx
     */
    private function checkStatusCode(ResponseInterface $response): void
    {
        try {
            $response->getHeaders();

            // Дополнительно проверить статус код, потому что исключение
            // ClientExceptionInterface или ServerExceptionInterface
            // может быть не вызвано, при указании соответствующей опции HTTP-запроса.
            if ($response->getStatusCode() >= 400) {
                throw new ParsingUnexpectedStatusCode($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_UNEXPECTED_CODE', ['STATUS_CODE' => $response->getStatusCode()]));
            }
        } catch (ClientExceptionInterface|ServerExceptionInterface) {
            throw new ParsingUnexpectedStatusCode($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_UNEXPECTED_CODE', ['STATUS_CODE' => $response->getStatusCode()]));
        }
    }

    /**
     * Проверяет ответ на запрос информации о компании на наличие необходимых данных.
     *
     * @param ResponseInterface $companyInfoResponse ответ на HTTP-запрос информации о компании
     *
     * @throws ParsingInvalidBody Если не удалось извлечь необходимые данные из тела запроса
     */
    private function checkCompanyInfoResponse(ResponseInterface $companyInfoResponse): void
    {
        $this->checkStatusCode($companyInfoResponse);

        $invalidBodyErrorMessage = $this->localization->getMessage(
            'LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_INVALID_BODY'
        );

        $contentType = $companyInfoResponse->getHeaders()['content-type'][0] ?? '';

        if (!str_starts_with($contentType, 'text/html')) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        try {
            $crawler = new Crawler($companyInfoResponse->getContent());

            $crawler->filter('h1.orgpage-header-view__header')->text();
            preg_replace(
                '/[^0-9,]+/',
                '',
                $crawler->filter('div.business-summary-rating-badge-view__rating')->text()
            );

            $crawler->filter('.card-section-header__title')->text();
            $crawler->filter('.business-rating-amount-view._summary')->text();
        } catch (\InvalidArgumentException) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }
    }

    /**
     * Непосредственно перезаписывает информацию о компании в БД актуальными данными.
     *
     * @param ResponseInterface $companyInfoResponse Ответ на запрос информации о компании
     */
    private function overwriteCompanyInfo(ResponseInterface $companyInfoResponse): void
    {
        $crawler = new Crawler($companyInfoResponse->getContent());

        $companyName = $crawler->filter('h1.orgpage-header-view__header')->text();
        $companyRating = preg_replace(
            '/[^0-9,]+/',
            '',
            $crawler->filter('div.business-summary-rating-badge-view__rating')->text()
        );

        $fullStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._full')
            ->count();
        $halfStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._half')
            ->count();
        $emptyStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._empty')
            ->count();

        $countReviews = $crawler
          ->filter('.card-section-header__title')
          ->text();
        $countMarks = $crawler
          ->filter('.business-rating-amount-view._summary')
          ->text();

        // Получить виртуальный класс объекта.
        $companyObject = new ($this->companyTable::getObjectClass());

        $companyObject
          ->setName($companyName)
          ->setRating($companyRating)
          ->setFullStars($fullStarsCount)
          ->setHalfStars($halfStarsCount)
          ->setEmptyStars($emptyStarsCount)
          ->setCountreviews($countReviews)
          ->setCountmarks($countMarks);

        $this->connection->truncateTable($this->companyTable::getTableName());
        $companyObject->save();
    }

    /**
     * Полностью перезаписывает отзывы о компании.
     *
     * Метод:
     * - Запрашивает последние отзывы.
     * - Проверяет ответ на наличие необходимых данных.
     * - Перезаписывает отзывы в БД последними (новыми) полученными.
     */
    private function fullReviewsOverwrite(): void
    {
        $latestReviewsResponse = $this->requestLatestReviews();

        $this->checkReviewsResponse($latestReviewsResponse);

        $this->overwriteReviews($latestReviewsResponse);
    }

    /**
     * Запрашивает последние отзывы о компании.
     *
     * @return ResponseInterface ответ на HTTP-запрос последних отзывов о компании
     */
    private function requestLatestReviews(): ResponseInterface
    {
        $companyId = $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'company_id');

        return $this->makeRequestWithHandleTimeout(
            'GET',
            self::REVIEWS_LIST_BASE_URL,
            [
                'query' => [
                    'offset' => 0,
                    'objectId' => "/sprav/$companyId",
                    'addComments' => 'true',
                    'otype' => 'Org', // Значение, которое должно быть в запросе.
                    'appId' => '1org-viewer', // Значение, которое должно быть в запросе.
                    'limit' => self::NUMBER_OF_REVIEWS_RECEIVED,
                ],
                'headers' => $this->generateRandomHeaders(),
            ]
        );
    }

    /**
     * Проверяет ответ на запрос последних отзывов на налчие нужных данных.
     *
     * @throws ParsingInvalidBody в случае отсутствия нужных данных в ответе
     */
    private function checkReviewsResponse(ResponseInterface $reviewsResponse): void
    {
        $this->checkStatusCode($reviewsResponse);

        $invalidBodyErrorMessage = $this->localization->getMessage(
            'LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_INVALID_BODY'
        );

        $contentType = $reviewsResponse->getHeaders()['content-type'][0] ?? '';

        if (!str_starts_with($contentType, 'application/json')) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        try {
            $decodedJson = $this->bitrixJson->decode($reviewsResponse->getContent());

            if (!is_array($decodedJson)) {
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
        } catch (ArgumentException) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        if (!is_array($decodedJson['view']['views'])) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        $reviews = array_slice($decodedJson['view']['views'], 1, -1);

        if (empty($reviews)) {
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        // Проверить структуру массиав на соответствие ожидамеой.
        foreach ($reviews as $review) {
            $unexpectedStructure = false;

            if (!is_array($review)) {
                $unexpectedStructure = true;
            }

            if (!is_string($review['author']['signPrivacy'])) {
                $unexpectedStructure = true;
            }

            if (!is_int($review['rating']['val'])) {
                $unexpectedStructure = true;
            }

            if (!is_int($review['time'])) {
                $unexpectedStructure = true;
            }

            if (!is_string($review['text'])) {
                $unexpectedStructure = true;
            }

            if ($unexpectedStructure) {
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
        }
    }

    /**
     * Непосредственно перезаписывает старые отзывы последними (новыми) в БД.
     *
     * @param ResponseInterface ответ на HTTP-запрос последних отзывов
     */
    private function overwriteReviews(
        ResponseInterface $response,
    ): void {
        $content = $this->bitrixJson->decode($response->getContent())['view']['views'];

        // Обрезать первый и последний элемент массива, т.к. в этих элементах системная информация.
        $reviews = array_slice($content, 1, -1);

        $reviewsCollection = new ($this->reviewsTable::getCollectionClass());

        foreach ($reviews as $review) {
            // Получить виртуальный класс объекта и установить ему значения.
            $reviewsCollection[] = (new ($this->reviewsTable::getObjectClass()))
                ->setName($this->getCorrectUserName($review['author']))
                ->setImage($this->getUserAvatarFullUrl($review['author']))
                ->setRating($review['rating']['val'])
                ->setData($review['time'] / 1000) // Конвертировать Unix time stamp в мс. в Unix time stamp в секундах.
                ->setTime($this->getReadableDate($review['time']))
                ->setDescription($review['text']);
        }

        $this->connection->truncateTable($this->reviewsTable::getTableName());
        $reviewsCollection->save();
    }

    /**
     * Возвращает корректное имя пользователя.
     *
     * В случае, если имя пользователя отсутствует в ответе - возвращается имя по умолчанию.
     */
    private function getCorrectUserName(array $authorInfo): string
    {
        if (!is_string($authorInfo['name'])) {
            return $this->localization::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_DEFAULT_NAME_FOR_REVIEWER');
        }

        return $authorInfo['name'];
    }

    /**
     * Возращает полный URL для получения аватара пользователя.
     */
    private function getUserAvatarFullUrl(array $authorInfo): string
    {
        if ('' === $authorInfo['pic']) {
            return '';
        }

        return self::USER_AVATAR_BASE_URL.'/'.$authorInfo['pic'].'/'.self::USER_AVATAR_SIZE;
    }

    /**
     * Генерирует случайные заголовки для HTTP-запроса.
     *
     * Метод используется для минимизации подозрительных HTTP-запросов.
     */
    private function generateRandomHeaders(): array
    {
        $oses = [
            'Windows NT 10.0; Win64; x64',
            'Windows NT 10.0; WOW64',
            'Windows NT 6.1; Win64; x64',
            'Macintosh; Intel Mac OS X 10_15',
            'Macintosh; Intel Mac OS X 11_0',
            'Macintosh; Intel Mac OS X 12_0',
            'X11; Linux x86_64',
        ];

        $majorVersions = range(115, 137);
        $rv = (string) $majorVersions[array_rand($majorVersions)];
        $version = $rv.'.0';

        $os = $oses[array_rand($oses)];

        $randomFireFoxDesktopUserAgent = sprintf(
            'Mozilla/5.0 (%s; rv:%s) Gecko/20100101 Firefox/%s',
            $os,
            $rv,
            $version
        );

        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection' => 'keep-alive',
            'Host' => 'yandex.ru',
            'Priority' => 'u=0, i',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'TE' => 'trailers',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => $randomFireFoxDesktopUserAgent,
        ];
    }

    /**
     * Конвертирует Unix-время (в мс.) в человекочитаемое время.
     *
     * @return string время в человекочитаемом формате
     */
    private function getReadableDate(int $unixTimeStampMs): string
    {
        $unixTimeStampS = intdiv($unixTimeStampMs, 1000);

        $currentYear = (int) date('Y');
        $dateYear = (int) date('Y', $unixTimeStampS);

        // Если год текущий.
        if ($dateYear === $currentYear) {
            return \formatDate('j F', $unixTimeStampS); // Отдаст «25 января».
        }

        // В ином случае.
        return \formatDate('j F Y', $unixTimeStampS); // Отдаст «25 января 2024».
    }
}
