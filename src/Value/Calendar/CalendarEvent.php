<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Calendar;

use DateTimeImmutable;
use JsonSerializable;

/**
 * What every CalendarProviderInterface implementation must produce. The UI and
 * CalendarService only ever see this — never a provider's raw event format.
 */
class CalendarEvent implements JsonSerializable
{
    private function __construct(
        private readonly string             $externalUid,
        private readonly int                $sourceId,
        private readonly string             $sourceName,
        private readonly ?string            $sourceColor,
        private readonly string             $title,
        private readonly DateTimeImmutable  $startsAt,
        private readonly DateTimeImmutable  $endsAt,
        private readonly bool               $isAllDay,
        private readonly ?string            $location,
        private readonly ?string            $description,
        /** @var string[] */
        private readonly array              $attendees,
        private readonly ?string            $url,
    ) {
    }

    /** @param string[] $attendees */
    public static function create(
        string            $externalUid,
        int               $sourceId,
        string            $sourceName,
        ?string           $sourceColor,
        string            $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        bool              $isAllDay,
        ?string           $location,
        ?string           $description,
        array             $attendees,
        ?string           $url,
    ): self {
        return new self(
            $externalUid,
            $sourceId,
            $sourceName,
            $sourceColor,
            $title,
            $startsAt,
            $endsAt,
            $isAllDay,
            $location,
            $description,
            $attendees,
            $url,
        );
    }

    public static function fromDatabase(array $row, string $sourceName, ?string $sourceColor): self
    {
        return new self(
            $row['external_uid'],
            (int) $row['source_id'],
            $sourceName,
            $sourceColor,
            $row['title'],
            new DateTimeImmutable($row['starts_at']),
            new DateTimeImmutable($row['ends_at']),
            (bool) $row['is_all_day'],
            $row['location'],
            $row['description'],
            $row['attendees'] !== null ? json_decode($row['attendees'], true, flags: JSON_THROW_ON_ERROR) : [],
            $row['url'],
        );
    }

    public function getExternalUid(): string
    {
        return $this->externalUid;
    }

    public function getSourceId(): int
    {
        return $this->sourceId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function isAllDay(): bool
    {
        return $this->isAllDay;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return string[] */
    public function getAttendees(): array
    {
        return $this->attendees;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => "{$this->sourceId}:{$this->externalUid}",
            'calendarId' => $this->sourceId,
            'title' => $this->title,
            'startsAt' => $this->startsAt->format('Y-m-d H:i:s'),
            'endsAt' => $this->endsAt->format('Y-m-d H:i:s'),
            'durationMinutes' => (int) (($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp()) / 60),
            'isAllDay' => $this->isAllDay,
            'location' => $this->location,
            'description' => $this->description,
            'attendees' => $this->attendees,
            'url' => $this->url,
            'calendar' => $this->sourceName,
            'color' => $this->sourceColor,
        ];
    }
}
