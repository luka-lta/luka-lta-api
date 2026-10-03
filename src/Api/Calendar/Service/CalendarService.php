<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Calendar\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Calendar\CalendarProviderResolver;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Repository\CalendarEventRepository;
use LukaLtaApi\Repository\CalendarSourceRepository;
use LukaLtaApi\Service\AlertManager;
use LukaLtaApi\Value\Calendar\CalendarSource;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Log\LoggerInterface;
use Throwable;

class CalendarService
{
    private const int SYNC_HORIZON_DAYS = 45;
    private const string SYNC_FAILURE_TYPE = 'calendar.sync_failed';
    private const int SYNC_FAILURE_THRESHOLD = 3;

    public function __construct(
        private readonly CalendarSourceRepository $sourceRepository,
        private readonly CalendarEventRepository  $eventRepository,
        private readonly CalendarProviderResolver $providerResolver,
        private readonly AlertManager             $alertManager,
        private readonly LoggerInterface          $logger,
    ) {
    }

    public function syncAll(): void
    {
        $now = new DateTimeImmutable();
        $from = $now->modify('-1 day');
        $to = $now->modify('+' . self::SYNC_HORIZON_DAYS . ' days');

        foreach ($this->sourceRepository->loadEnabled() as $source) {
            $sourceId = (string) $source->getId();
            $title = "Calendar \"{$source->getName()}\" sync failing";

            try {
                $provider = $this->providerResolver->resolve($source->getType());
                $events = $provider->fetchEvents($source, $from, $to);
                $this->eventRepository->replaceForSource((int) $source->getId(), $events, $now);

                $this->alertManager->evaluate(
                    false,
                    'calendar',
                    $sourceId,
                    self::SYNC_FAILURE_TYPE,
                    'warning',
                    $title,
                    '',
                    [],
                    self::SYNC_FAILURE_THRESHOLD,
                );
            } catch (Throwable $exception) {
                $this->alertManager->evaluate(
                    true,
                    'calendar',
                    $sourceId,
                    self::SYNC_FAILURE_TYPE,
                    'warning',
                    $title,
                    $exception->getMessage(),
                    ['sourceId' => $source->getId(), 'sourceName' => $source->getName()],
                    self::SYNC_FAILURE_THRESHOLD,
                );

                $this->logger->error('Calendar source sync failed', [
                    'sourceId' => $source->getId(),
                    'sourceName' => $source->getName(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function getSummary(): ApiResult
    {
        $sources = $this->sourceRepository->loadEnabled();
        if (empty($sources)) {
            return ApiResult::from(JsonResult::from('Calendar not configured.', [
                'status' => 'not_configured',
                'events' => [],
            ]));
        }

        $now = new DateTimeImmutable();
        $events = $this->eventRepository->loadBetween($now->setTime(0, 0), $now->setTime(23, 59, 59));

        return ApiResult::from(JsonResult::from('Calendar events fetched.', [
            'status' => 'ok',
            'events' => $events,
        ]));
    }

    public function getEvents(DateTimeImmutable $from, DateTimeImmutable $to): ApiResult
    {
        $sources = $this->sourceRepository->loadEnabled();
        if (empty($sources)) {
            return ApiResult::from(JsonResult::from('Calendar not configured.', [
                'status' => 'not_configured',
                'events' => [],
            ]));
        }

        return ApiResult::from(JsonResult::from('Calendar events fetched.', [
            'status' => 'ok',
            'events' => $this->eventRepository->loadBetween($from, $to),
        ]));
    }

    public function listSources(): ApiResult
    {
        return ApiResult::from(JsonResult::from('Calendar sources fetched.', [
            'sources' => $this->sourceRepository->loadAll(),
        ]));
    }

    public function createSource(array $data): ApiResult
    {
        if (!isset($data['name'], $data['url'])) {
            throw new ApiValidationException('name and url are required.', 400);
        }

        if (filter_var($data['url'], FILTER_VALIDATE_URL) === false) {
            throw new ApiValidationException('Please enter a valid URL.', 400);
        }

        $type = (string) ($data['type'] ?? 'ics');
        $provider = $this->providerResolver->resolve($type);

        $source = $this->sourceRepository->create(
            CalendarSource::create($type, (string) $data['name'], (string) $data['url'], $data['color'] ?? null),
        );

        // Fail fast with a real error instead of a silently-broken calendar: the
        // first sync happens right here so an unreachable/invalid ICS URL is
        // rejected immediately, not discovered 10 minutes later by the cron.
        $now = new DateTimeImmutable();
        try {
            $events = $provider->fetchEvents(
                $source,
                $now->modify('-1 day'),
                $now->modify('+' . self::SYNC_HORIZON_DAYS . ' days'),
            );
            $this->eventRepository->replaceForSource((int) $source->getId(), $events, $now);
        } catch (Throwable $exception) {
            $this->sourceRepository->delete((int) $source->getId());
            throw new ApiValidationException(
                'This calendar could not be reached. Please check the ICS URL.',
                400,
                $exception,
            );
        }

        return ApiResult::from(
            JsonResult::from('Calendar source created.', ['source' => $source]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function updateSource(int $id, array $data): ApiResult
    {
        if (isset($data['url']) && filter_var($data['url'], FILTER_VALIDATE_URL) === false) {
            throw new ApiValidationException('Please enter a valid URL.', 400);
        }

        $source = $this->sourceRepository->update($id, $data);

        return ApiResult::from(JsonResult::from('Calendar source updated.', ['source' => $source]));
    }

    public function deleteSource(int $id): ApiResult
    {
        $this->sourceRepository->delete($id);

        return ApiResult::from(JsonResult::from('Calendar source deleted.'), StatusCodeInterface::STATUS_NO_CONTENT);
    }
}
