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
        file_put_contents(__DIR__.'/test.txt', 'getReviewsForComponent| ', FILE_APPEND);
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
        file_put_contents(__DIR__.'/test.txt', 'getCompanyInfoForComponent',    FILE_APPEND);
        return $this->companyTable::getList()->fetchObject();
    }

    /**
     * Возвращает URL страницы отзывов на Яндекс Картах.
     */
    public function getCompanyReviewsPageUrl(): string
    {
        file_put_contents(__DIR__.'/test.txt', 'getCompanyReviewsPageUrl| ', FILE_APPEND);
        $companyId = $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'company_id');

        return self::COMPANY_YANDEX_MAPS_REVIEWS_PAGE_BASE_URL.'/'.$companyId.'/reviews/';
    }

    /**
     * Возвращает URL, по которому можно оставить отзыв о компании на Яндекс Картах.
     */
    public function getUrlForAddReview(): string
    {
        file_put_contents(__DIR__.'/test.txt', 'getUrlForAddReview| ', FILE_APPEND);
        return $this->getCompanyReviewsPageUrl().'?add-review=true';
    }

    /**
     * Возвращает значение параметра "Скрыть логотип разработчика".
     */
    public function requiredToHideLogoInComponent(): bool
    {
        file_put_contents(__DIR__.'/test.txt', 'requiredToHideLogoInComponent| ', FILE_APPEND);
        return 'Y' === $this->bitrixModuleOptions->get('leadspace.yandexreviewsslider', 'hide_logo');
    }

    /**
     * Проверяет заполненность обязательных параметров
     * модуля перед перезаписью информации о компании и отзывов.
     */
    private function checkRequiredModuleParams(): void
    {
        file_put_contents(__DIR__.'/test.txt', 'checkRequiredModuleParams| ', FILE_APPEND);
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
        file_put_contents(__DIR__.'/test.txt', 'fullCompanyInfoOverwrite| end| ', FILE_APPEND);
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
    $startTime = microtime(true);
    
    try {
        $response = $this->httpClient->request(
            $method,
            $url,
            $options
        );
        
        $responseTime = microtime(true) - $startTime;
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false); // false - не бросать исключение при ошибке
        $headers = $response->getHeaders();
        
        // Логирование успешного запроса с деталями ответа
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'status' => 'success',
            'status_code' => $statusCode,
            'response_time' => round($responseTime, 3) . 's',
            'headers' => $this->getHeadersSummary($headers),
            'body_length' => strlen($content),
            'content_type' => $headers['content-type'][0] ?? 'unknown',
        ];
        
        // Добавляем превью тела для отладки (только для текстовых типов контента)
        if ($this->isTextContent($headers['content-type'][0] ?? '')) {
            $logData['body_preview'] = substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '');
        }
        
        file_put_contents(__DIR__ . '/request_log.txt', json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
        
        return $response;
        
    } catch (TimeoutException $e) {
        $responseTime = microtime(true) - $startTime;
        
        // Логирование таймаута
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'status' => 'timeout',
            'response_time' => round($responseTime, 3) . 's',
            'error' => 'Request timeout exceeded',
            'error_message' => $e->getMessage()
        ];
        
        file_put_contents(__DIR__ . '/request_log.txt', json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
        
        throw new ParsingRequestTimeout($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_REQUEST_TIMEOUT'));
    } catch (\Exception $e) {
        $responseTime = microtime(true) - $startTime;
        
        // Логирование других ошибок
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'status' => 'error',
            'response_time' => round($responseTime, 3) . 's',
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'error_trace' => $this->getShortTrace($e)
        ];
        
        file_put_contents(__DIR__ . '/request_log.txt', json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
        
        throw $e;
    }
}

private function getHeadersSummary(array $headers): array
{
    file_put_contents(__DIR__.'/test.txt', 'getHeadersSummary| ', FILE_APPEND);
    $summary = [];
    foreach ($headers as $name => $values) {
        $summary[$name] = is_array($values) ? implode(', ', $values) : $values;
    }
    return $summary;
}

private function isTextContent(string $contentType): bool
{
    file_put_contents(__DIR__.'/test.txt', 'isTextContent| ', FILE_APPEND);
    $textTypes = [
        'text/',
        'application/json',
        'application/xml',
        'application/xhtml+xml',
        'application/javascript',
        'application/x-www-form-urlencoded'
    ];
    
    foreach ($textTypes as $textType) {
        if (strpos($contentType, $textType) === 0) {
            return true;
        }
    }
    
    return false;
}

private function getShortTrace(\Exception $e): array
{
    file_put_contents(__DIR__.'/test.txt', 'getShortTrace| ', FILE_APPEND);
    $trace = $e->getTrace();
    $shortTrace = [];
    
    // Берем только первые 3 элемента трейса для логирования
    for ($i = 0; $i < min(3, count($trace)); $i++) {
        $shortTrace[] = [
            'file' => $trace[$i]['file'] ?? 'unknown',
            'line' => $trace[$i]['line'] ?? 'unknown',
            'function' => $trace[$i]['function'] ?? 'unknown'
        ];
    }
    
    return $shortTrace;
}

    /**
     * Запрашивает информацию о компании с Яндекс Карт.
     *
     * @return ResponseInterface результат запроса
     */
    private function requestLatestCompanyInfo(): ResponseInterface
    {
        file_put_contents(__DIR__.'/test.txt', 'requestLatestCompanyInfo| ', FILE_APPEND);
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
            file_put_contents(__DIR__.'/test.txt', 'checkStatusCode| '. $response->getStatusCode().'| ', FILE_APPEND);
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
    file_put_contents(__DIR__.'/test.txt', 'checkCompanyInfoResponse| ', FILE_APPEND);
    $this->checkStatusCode($companyInfoResponse);

    $invalidBodyErrorMessage = $this->localization->getMessage(
        'LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_INVALID_BODY'
    );

    $content = $companyInfoResponse->getContent();
    $contentType = $companyInfoResponse->getHeaders()['content-type'][0] ?? '';
    file_put_contents(__DIR__.'/test.txt', 'contentType: ' . $contentType . '| ', FILE_APPEND);

    // Сохраняем содержимое для отладки
    file_put_contents(__DIR__.'/debug_company_page.html', $content);

    // Проверяем на наличие капчи
    if (strpos($content, 'SmartCaptcha') !== false || 
        strpos($content, 'confirm you are not a robot') !== false ||
        strpos($content, 'подтвердите, что запросы отправляли вы') !== false) {
        file_put_contents(__DIR__.'/test.txt', 'CAPTCHA_DETECTED| ', FILE_APPEND);
        throw new ParsingInvalidBody($this->localization->getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_CAPTCHA_DETECTED'));
    }

    if (!str_starts_with($contentType, 'text/html')) {
        file_put_contents(__DIR__.'/test.txt', 'NOT_HTML_CONTENT| ', FILE_APPEND);
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    }

    try {
        $crawler = new Crawler($companyInfoResponse->getContent());
        file_put_contents(__DIR__.'/test.txt', 'crawler_created| ', FILE_APPEND);

        // Проверка заголовка h1 - несколько возможных селекторов
        $h1Selectors = [
            'h1.orgpage-header-view__header',
            'h1.card-title-view__title',
            'h1', // fallback на любой h1
            '.orgpage-header-view__header', // может быть div вместо h1
            '.card-title-view__title', // альтернативный селектор
        ];
        
        $h1Found = false;
        $h1Text = '';
        
        foreach ($h1Selectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $h1Text = $crawler->filter($selector)->text();
                $h1Found = true;
                file_put_contents(__DIR__.'/test.txt', 'h1_found_via_' . $selector . ': ' . substr($h1Text, 0, 50) . '| ', FILE_APPEND);
                break;
            }
        }
        
        if (!$h1Found) {
            file_put_contents(__DIR__.'/test.txt', 'H1_NOT_FOUND| ', FILE_APPEND);
            // Проверим другие признаки существования компании
            $companyNameSelectors = [
                '[data-org-name]',
                '.business-card-title-view__title',
                '.orgpage-name',
                '.organization-name',
            ];
            
            foreach ($companyNameSelectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $h1Text = $crawler->filter($selector)->text();
                    $h1Found = true;
                    file_put_contents(__DIR__.'/test.txt', 'company_name_found_via_' . $selector . ': ' . substr($h1Text, 0, 50) . '| ', FILE_APPEND);
                    break;
                }
            }
            
            if (!$h1Found) {
                file_put_contents(__DIR__.'/test.txt', 'NO_COMPANY_NAME_FOUND| ', FILE_APPEND);
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
        }

        // Проверка рейтинга - если не найден, возвращаем 5
        $rating = '5'; // значение по умолчанию
        $ratingFound = false;
        $ratingSelectors = [
            'div.business-summary-rating-badge-view__rating',
            '.business-rating-badge-view__rating-text',
            '.business-rating-view__rating',
            '[data-rating]',
            '.rating-value',
            // добавьте другие возможные селекторы рейтинга
        ];
        
        foreach ($ratingSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                try {
                    $ratingText = $crawler->filter($selector)->text();
                    $rating = preg_replace('/[^0-9,]+/', '', $ratingText);
                    file_put_contents(__DIR__.'/test.txt', 'rating_found_via_' . $selector . ': ' . $rating . '| ', FILE_APPEND);
                    $ratingFound = true;
                    break;
                } catch (\Exception $e) {
                    file_put_contents(__DIR__.'/test.txt', 'rating_error_' . $selector . ': ' . $e->getMessage() . '| ', FILE_APPEND);
                    continue;
                }
            }
        }
        
        if (!$ratingFound) {
            file_put_contents(__DIR__.'/test.txt', 'RATING_NOT_FOUND_USING_DEFAULT_5| ', FILE_APPEND);
        }

        // Проверка заголовка секции с несколькими селекторами
        $sectionFound = false;
        $sectionSelectors = [
            '.card-section-header__title._wide',
            '.card-section-header__title',
            '.reviews-section-title',
            '.section-header-title',
            'h2', // fallback на любой h2
        ];
        
        foreach ($sectionSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                try {
                    $sectionTitle = $crawler->filter($selector)->text();
                    file_put_contents(__DIR__.'/test.txt', 'section_title_via_' . $selector . ': ' . $sectionTitle . '| ', FILE_APPEND);
                    $sectionFound = true;
                    break;
                } catch (\Exception $e) {
                    file_put_contents(__DIR__.'/test.txt', 'section_error_' . $selector . ': ' . $e->getMessage() . '| ', FILE_APPEND);
                    continue;
                }
            }
        }
        
        if (!$sectionFound) {
            file_put_contents(__DIR__.'/test.txt', 'SECTION_TITLE_NOT_FOUND| ', FILE_APPEND);
        }

        // Проверка количества отзывов
        $reviewsCount = null;
        $reviewsSelectors = [
            '.business-rating-amount-view._summary',
            '.business-rating-amount-view',
            '.reviews-count',
            '.rating-count',
            '[data-review-count]',
            '[data-reviews-count]',
            // добавьте другие возможные селекторы количества отзывов
        ];
        
        foreach ($reviewsSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                try {
                    $reviewsCount = $crawler->filter($selector)->text();
                    file_put_contents(__DIR__.'/test.txt', 'reviews_found_via_' . $selector . ': ' . $reviewsCount . '| ', FILE_APPEND);
                    break;
                } catch (\Exception $e) {
                    file_put_contents(__DIR__.'/test.txt', 'reviews_error_' . $selector . ': ' . $e->getMessage() . '| ', FILE_APPEND);
                    continue;
                }
            }
        }
        
        if ($reviewsCount === null) {
            file_put_contents(__DIR__.'/test.txt', 'REVIEWS_COUNT_NOT_FOUND| ', FILE_APPEND);
        }

    } catch (\InvalidArgumentException $e) {
        file_put_contents(__DIR__.'/test.txt', 'InvalidArgumentException: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'GeneralException: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    }
    
    file_put_contents(__DIR__.'/test.txt', 'checkCompanyInfoResponse_success| ', FILE_APPEND);
}

    /**
     * Непосредственно перезаписывает информацию о компании в БД актуальными данными.
     *
     * @param ResponseInterface $companyInfoResponse Ответ на запрос информации о компании
     */
private function overwriteCompanyInfo(ResponseInterface $companyInfoResponse): void
{
    file_put_contents(__DIR__.'/test.txt', 'overwriteCompanyInfo| ', FILE_APPEND);
    $crawler = new Crawler($companyInfoResponse->getContent());
    file_put_contents(__DIR__.'/test.txt', 'crawler_created| ', FILE_APPEND);

    try {
        // Логируем название компании
        $companyName = $crawler->filter('h1.orgpage-header-view__header')->text();
        file_put_contents(__DIR__.'/test.txt', 'company_name: ' . $companyName . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'company_name_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw $e;
    }

    try {
        // Логируем рейтинг
        $ratingText = $crawler->filter('div.business-summary-rating-badge-view__rating')->text();
        file_put_contents(__DIR__.'/test.txt', 'rating_raw: ' . $ratingText . '| ', FILE_APPEND);
        
        $companyRating = preg_replace('/[^0-9,]+/', '', $ratingText);
        file_put_contents(__DIR__.'/test.txt', 'rating_cleaned: ' . $companyRating . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'rating_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        // Используем значение по умолчанию как в checkCompanyInfoResponse
        $companyRating = '5';
        file_put_contents(__DIR__.'/test.txt', 'using_default_rating: ' . $companyRating . '| ', FILE_APPEND);
    }

    // Логируем звезды
    try {
        $fullStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._full')
            ->count();
        file_put_contents(__DIR__.'/test.txt', 'full_stars: ' . $fullStarsCount . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'full_stars_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        $fullStarsCount = 0;
    }

    try {
        $halfStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._half')
            ->count();
        file_put_contents(__DIR__.'/test.txt', 'half_stars: ' . $halfStarsCount . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'half_stars_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        $halfStarsCount = 0;
    }

    try {
        $emptyStarsCount = $crawler
            ->filter('.business-summary-rating-badge-view__stars-and-count .business-rating-badge-view__star._empty')
            ->count();
        file_put_contents(__DIR__.'/test.txt', 'empty_stars: ' . $emptyStarsCount . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'empty_stars_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        $emptyStarsCount = 0;
    }

    // Логируем количество отзывов
    try {
        $countReviews = $crawler->filter('.card-section-header__title')->text();
        file_put_contents(__DIR__.'/test.txt', 'count_reviews_raw: ' . $countReviews . '| ', FILE_APPEND);
        
        // Извлекаем только цифры из текста "1 отзыв"
        $countReviews = preg_replace('/[^0-9]+/', '', $countReviews);
        file_put_contents(__DIR__.'/test.txt', 'count_reviews_cleaned: ' . $countReviews . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'count_reviews_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        $countReviews = '0';
    }

    // Логируем количество оценок
    try {
        $countMarks = $crawler->filter('.business-rating-amount-view._summary')->text();
        file_put_contents(__DIR__.'/test.txt', 'count_marks_raw: ' . $countMarks . '| ', FILE_APPEND);
        
        // Извлекаем только цифры
        $countMarks = preg_replace('/[^0-9]+/', '', $countMarks);
        file_put_contents(__DIR__.'/test.txt', 'count_marks_cleaned: ' . $countMarks . '| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'count_marks_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        $countMarks = '0';
    }

    // Логируем создание объекта
    file_put_contents(__DIR__.'/test.txt', 'creating_company_object| ', FILE_APPEND);
    $companyObject = new ($this->companyTable::getObjectClass());

    try {
        $companyObject
          ->setName($companyName)
          ->setRating($companyRating)
          ->setFullStars($fullStarsCount)
          ->setHalfStars($halfStarsCount)
          ->setEmptyStars($emptyStarsCount)
          ->setCountreviews($countReviews)
          ->setCountmarks($countMarks);
        
        file_put_contents(__DIR__.'/test.txt', 'object_properties_set| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'set_properties_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw $e;
    }

    // Логируем операции с базой данных
    try {
        file_put_contents(__DIR__.'/test.txt', 'truncating_table| ', FILE_APPEND);
        $this->connection->truncateTable($this->companyTable::getTableName());
        file_put_contents(__DIR__.'/test.txt', 'table_truncated| ', FILE_APPEND);
        
        file_put_contents(__DIR__.'/test.txt', 'saving_object| ', FILE_APPEND);
        $companyObject->save();
        file_put_contents(__DIR__.'/test.txt', 'object_saved| ', FILE_APPEND);
        
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'db_operation_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw $e;
    }

    file_put_contents(__DIR__.'/test.txt', 'overwriteCompanyInfo_success| ', FILE_APPEND);
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
    file_put_contents(__DIR__.'/test.txt', 'fullReviewsOverwrite| start| ', FILE_APPEND);
    try {
        $latestReviewsResponse = $this->requestLatestReviews();
        file_put_contents(__DIR__.'/test.txt', 'requestLatestReviews_done| ', FILE_APPEND);

        $this->checkReviewsResponse($latestReviewsResponse);
        file_put_contents(__DIR__.'/test.txt', 'checkReviewsResponse_done| ', FILE_APPEND);

        $this->overwriteReviews($latestReviewsResponse);
        file_put_contents(__DIR__.'/test.txt', 'overwriteReviews_done| ', FILE_APPEND);
        
        file_put_contents(__DIR__.'/test.txt', 'fullReviewsOverwrite_success| ', FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'fullReviewsOverwrite_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw $e;
    }
}

    /**
     * Запрашивает последние отзывы о компании.
     *
     * @return ResponseInterface ответ на HTTP-запрос последних отзывов о компании
     */
    private function requestLatestReviews(): ResponseInterface
    {
        file_put_contents(__DIR__.'/test.txt', 'requestLatestReviews| ', FILE_APPEND);
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
    file_put_contents(__DIR__.'/test.txt', 'checkReviewsResponse| ', FILE_APPEND);
    $this->checkStatusCode($reviewsResponse);

    $invalidBodyErrorMessage = $this->localization->getMessage(
        'LEADSPACE_YANDEXREVIEWSSLIDER_PARSING_INVALID_BODY'
    );

    $contentType = $reviewsResponse->getHeaders()['content-type'][0] ?? '';
    file_put_contents(__DIR__.'/test.txt', 'contentType: ' . $contentType . '| ', FILE_APPEND);

    // Сохраняем содержимое для отладки
    $content = $reviewsResponse->getContent();
    file_put_contents(__DIR__.'/debug_reviews_response.html', $content);
    file_put_contents(__DIR__.'/test.txt', 'content_length: ' . strlen($content) . '| ', FILE_APPEND);

    if (!str_starts_with($contentType, 'application/json')) {
        file_put_contents(__DIR__.'/test.txt', 'NOT_JSON_CONTENT| ', FILE_APPEND);
        
        // Проверим, может быть это ошибка или капча
        if (strpos($content, 'captcha') !== false) {
            file_put_contents(__DIR__.'/test.txt', 'CAPTCHA_DETECTED| ', FILE_APPEND);
        }
        if (strpos($content, 'error') !== false) {
            file_put_contents(__DIR__.'/test.txt', 'ERROR_PAGE| ', FILE_APPEND);
        }
        
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    }

    try {
        $content = $reviewsResponse->getContent();
        file_put_contents(__DIR__.'/test.txt', 'response_content_length: ' . strlen($content) . '| ', FILE_APPEND);
        
        // Сохраняем JSON для отладки
        file_put_contents(__DIR__.'/debug_reviews.json', $content);
        
        $decodedJson = $this->bitrixJson->decode($content);
        file_put_contents(__DIR__.'/test.txt', 'json_decoded| ', FILE_APPEND);

        if (!is_array($decodedJson)) {
            file_put_contents(__DIR__.'/test.txt', 'JSON_NOT_ARRAY| ', FILE_APPEND);
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }
        
        // Логируем ключи JSON для отладки
        $jsonKeys = array_keys($decodedJson);
        file_put_contents(__DIR__.'/test.txt', 'json_keys: ' . implode(',', $jsonKeys) . '| ', FILE_APPEND);
        
        // НОВЫЙ КОД: Проверяем новую структуру с view->views
        if (!isset($decodedJson['view'])) {
            file_put_contents(__DIR__.'/test.txt', 'NO_VIEW_KEY| ', FILE_APPEND);
            // Попробуем старую структуру для обратной совместимости
            if (!isset($decodedJson['reviews'])) {
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
            $reviews = $decodedJson['reviews'];
        } else {
            // Новая структура: view->views
            if (!isset($decodedJson['view']['views'])) {
                file_put_contents(__DIR__.'/test.txt', 'NO_VIEWS_KEY| ', FILE_APPEND);
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
            
            $views = $decodedJson['view']['views'];
            file_put_contents(__DIR__.'/test.txt', 'views_count: ' . count($views) . '| ', FILE_APPEND);
            
            if (!is_array($views)) {
                file_put_contents(__DIR__.'/test.txt', 'VIEWS_NOT_ARRAY| ', FILE_APPEND);
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }

            // Фильтруем только отзывы (type = "/ugc/review")
            $reviews = array_filter($views, function($item) {
                return isset($item['type']) && $item['type'] === '/ugc/review';
            });
            
            $reviews = array_values($reviews);
            file_put_contents(__DIR__.'/test.txt', 'reviews_after_filter: ' . count($reviews) . '| ', FILE_APPEND);
        }

        if (empty($reviews)) {
            file_put_contents(__DIR__.'/test.txt', 'NO_REVIEWS_FOUND| ', FILE_APPEND);
            throw new ParsingInvalidBody($invalidBodyErrorMessage);
        }

        // Проверить структуру массива на соответствие ожидаемой.
        foreach ($reviews as $index => $review) {
            $unexpectedStructure = false;

            if (!is_array($review)) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NOT_ARRAY| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if (!isset($review['author']) || !is_array($review['author'])) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NO_AUTHOR| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if (!isset($review['author']['signPrivacy']) || !is_string($review['author']['signPrivacy'])) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NO_SIGNPRIVACY| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if (!isset($review['rating']) || !is_array($review['rating']) || !isset($review['rating']['val']) || !is_int($review['rating']['val'])) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NO_RATING_VAL| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if (!isset($review['time']) || !is_int($review['time'])) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NO_TIME| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if (!isset($review['text']) || !is_string($review['text'])) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_NO_TEXT| ', FILE_APPEND);
                $unexpectedStructure = true;
            }

            if ($unexpectedStructure) {
                file_put_contents(__DIR__.'/test.txt', 'REVIEW_' . $index . '_UNEXPECTED_STRUCTURE| ', FILE_APPEND);
                throw new ParsingInvalidBody($invalidBodyErrorMessage);
            }
        }
        
        file_put_contents(__DIR__.'/test.txt', 'checkReviewsResponse_success| ', FILE_APPEND);

    } catch (ArgumentException $e) {
        file_put_contents(__DIR__.'/test.txt', 'JSON_DECODE_ERROR: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'UNEXPECTED_ERROR: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw new ParsingInvalidBody($invalidBodyErrorMessage);
    }
}

    /**
     * Непосредственно перезаписывает старые отзывы последними (новыми) в БД.
     *
     * @param ResponseInterface ответ на HTTP-запрос последних отзывов
     */
private function overwriteReviews(ResponseInterface $response): void
{
    file_put_contents(__DIR__.'/test.txt', 'overwriteReviews| ', FILE_APPEND);
    
    try {
        $content = $response->getContent();
        $decoded = $this->bitrixJson->decode($content);
        file_put_contents(__DIR__.'/test.txt', 'json_decoded| ', FILE_APPEND);
        
        // НОВЫЙ КОД: Получаем отзывы из новой или старой структуры
        if (isset($decoded['view']['views'])) {
            // Новая структура: view->views
            $views = $decoded['view']['views'];
            file_put_contents(__DIR__.'/test.txt', 'views_count: ' . count($views) . '| ', FILE_APPEND);
            
            // Фильтруем только отзывы (type = "/ugc/review")
            $reviews = array_filter($views, function($item) {
                return isset($item['type']) && $item['type'] === '/ugc/review';
            });
            
            $reviews = array_values($reviews);
            file_put_contents(__DIR__.'/test.txt', 'reviews_after_filter: ' . count($reviews) . '| ', FILE_APPEND);
            
        } elseif (isset($decoded['reviews'])) {
            // Старая структура: reviews
            $reviews = $decoded['reviews'];
            file_put_contents(__DIR__.'/test.txt', 'reviews_count: ' . count($reviews) . '| ', FILE_APPEND);
            
            // Обрезать первый и последний элемент массива для старой структуры
            $reviews = array_slice($reviews, 1, -1);
            file_put_contents(__DIR__.'/test.txt', 'reviews_after_slice: ' . count($reviews) . '| ', FILE_APPEND);
            
        } else {
            file_put_contents(__DIR__.'/test.txt', 'NO_REVIEWS_IN_JSON| ', FILE_APPEND);
            throw new \Exception('No reviews found in JSON response');
        }

        if (empty($reviews)) {
            file_put_contents(__DIR__.'/test.txt', 'NO_REVIEWS_TO_PROCESS| ', FILE_APPEND);
            throw new \Exception('No reviews to process');
        }

        $reviewsCollection = new ($this->reviewsTable::getCollectionClass());
        file_put_contents(__DIR__.'/test.txt', 'collection_created| ', FILE_APPEND);

        foreach ($reviews as $index => $review) {
            try {
                $reviewObject = new ($this->reviewsTable::getObjectClass());
                
                $userName = $this->getCorrectUserName($review['author']);
                $avatarUrl = $this->getUserAvatarFullUrl($review['author']);
                $rating = $review['rating']['val'];
                $timestamp = $review['time'] / 1000;
                $readableDate = $this->getReadableDate($review['time']);
                $text = $review['text'];
                
                $reviewObject
                    ->setName($userName)
                    ->setImage($avatarUrl)
                    ->setRating($rating)
                    ->setData($timestamp)
                    ->setTime($readableDate)
                    ->setDescription($text);
                
                $reviewsCollection[] = $reviewObject;
                
                file_put_contents(__DIR__.'/test.txt', 'review_' . $index . '_added: ' . substr($text, 0, 50) . '| ', FILE_APPEND);
                
            } catch (\Exception $e) {
                file_put_contents(__DIR__.'/test.txt', 'review_' . $index . '_error: ' . $e->getMessage() . '| ', FILE_APPEND);
                // Продолжаем обработку остальных отзывов
                continue;
            }
        }

        file_put_contents(__DIR__.'/test.txt', 'truncating_reviews_table| ', FILE_APPEND);
        $this->connection->truncateTable($this->reviewsTable::getTableName());
        file_put_contents(__DIR__.'/test.txt', 'table_truncated| ', FILE_APPEND);
        
        file_put_contents(__DIR__.'/test.txt', 'saving_collection| ', FILE_APPEND);
        $reviewsCollection->save();
        file_put_contents(__DIR__.'/test.txt', 'collection_saved| ', FILE_APPEND);
        
        file_put_contents(__DIR__.'/test.txt', 'overwriteReviews_success| ', FILE_APPEND);

    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/test.txt', 'overwriteReviews_error: ' . $e->getMessage() . '| ', FILE_APPEND);
        throw $e;
    }
}

    /**
     * Возвращает корректное имя пользователя.
     *
     * В случае, если имя пользователя отсутствует в ответе - возвращается имя по умолчанию.
     */
private function getCorrectUserName(array $authorInfo): string
{
    file_put_contents(__DIR__.'/test.txt', 'getCorrectUserName| ', FILE_APPEND);
    if (!isset($authorInfo['name']) || !is_string($authorInfo['name'])) {
        file_put_contents(__DIR__.'/test.txt', 'using_default_name| ', FILE_APPEND);
        return $this->localization::getMessage('LEADSPACE_YANDEXREVIEWSSLIDER_DEFAULT_NAME_FOR_REVIEWER');
    }

    file_put_contents(__DIR__.'/test.txt', 'user_name: ' . $authorInfo['name'] . '| ', FILE_APPEND);
    return $authorInfo['name'];
}

    /**
     * Возращает полный URL для получения аватара пользователя.
     */
private function getUserAvatarFullUrl(array $authorInfo): string
{
    file_put_contents(__DIR__.'/test.txt', 'getUserAvatarFullUrl| ', FILE_APPEND);
    if (!isset($authorInfo['pic']) || '' === $authorInfo['pic']) {
        file_put_contents(__DIR__.'/test.txt', 'no_avatar| ', FILE_APPEND);
        return '';
    }

    $avatarUrl = self::USER_AVATAR_BASE_URL.'/'.$authorInfo['pic'].'/'.self::USER_AVATAR_SIZE;
    file_put_contents(__DIR__.'/test.txt', 'avatar_url: ' . $avatarUrl . '| ', FILE_APPEND);
    return $avatarUrl;
}

    /**
     * Генерирует случайные заголовки для HTTP-запроса.
     *
     * Метод используется для минимизации подозрительных HTTP-запросов.
     */
    private function generateRandomHeaders(): array
    {
        file_put_contents(__DIR__.'/test.txt', 'generateRandomHeaders| ', FILE_APPEND);
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
        file_put_contents(__DIR__.'/test.txt', 'getReadableDate| ', FILE_APPEND);
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
