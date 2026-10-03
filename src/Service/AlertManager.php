<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use DateTimeImmutable;
use LukaLtaApi\Repository\AlertFailureCounterRepository;
use LukaLtaApi\Repository\AlertRepository;
use LukaLtaApi\Value\Alert\Alert;

class AlertManager
{
    public function __construct(
        private readonly AlertRepository $alertRepository,
        private readonly AlertFailureCounterRepository $counterRepository,
    ) {
    }

    public function evaluate(
        bool    $isTriggered,
        string  $source,
        ?string $sourceId,
        string  $type,
        string  $severity,
        string  $title,
        string  $description,
        array   $context = [],
        int     $consecutiveThreshold = 1,
    ): ?AlertTransition {
        $fingerprint = $this->fingerprint($source, $sourceId, $type);
        $now = new DateTimeImmutable();

        if ($isTriggered) {
            if ($consecutiveThreshold > 1) {
                $count = $this->counterRepository->incrementAndGet($fingerprint, $now);
                if ($count < $consecutiveThreshold) {
                    return null;
                }

                $this->counterRepository->reset($fingerprint);
            }

            $activeAlert = $this->alertRepository->findActiveByFingerprint($fingerprint);
            if ($activeAlert === null) {
                $this->alertRepository->create(
                    Alert::create($fingerprint, $source, $sourceId, $type, $severity, $title, $description, $context),
                );
                return AlertTransition::Created;
            }

            $this->alertRepository->bumpOccurrence($activeAlert->getAlertId(), $description, $context, $now);
            return AlertTransition::Bumped;
        }

        if ($consecutiveThreshold > 1) {
            $this->counterRepository->reset($fingerprint);
        }

        $activeAlert = $this->alertRepository->findActiveByFingerprint($fingerprint);
        if ($activeAlert !== null) {
            $this->alertRepository->resolve($activeAlert->getAlertId(), $now);
            return AlertTransition::Resolved;
        }

        return null;
    }

    private function fingerprint(string $source, ?string $sourceId, string $type): string
    {
        return hash('sha256', "{$source}:{$type}:" . ($sourceId ?? 'global'));
    }
}
