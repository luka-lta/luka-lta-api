<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Homelab;

use DateTimeImmutable;
use LukaLtaApi\Repository\HomelabEventRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Service\AlertManager;
use LukaLtaApi\Service\AlertTransition;
use LukaLtaApi\Value\Homelab\Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'homelab:check-heartbeats',
    description: 'Raises or resolves an alert for any host that has stopped reporting metrics.',
)]
class CheckHomelabHeartbeatsCommand extends Command
{
    private const OFFLINE_THRESHOLD_SECONDS = 900;
    private const string CRON_SELF_FAILURE_TYPE = 'cron.failed';
    private const int CRON_SELF_FAILURE_THRESHOLD = 2;

    public function __construct(
        private readonly HomelabHostRepository  $hostRepository,
        private readonly HomelabEventRepository $eventRepository,
        private readonly AlertManager           $alertManager,
        private readonly LoggerInterface        $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $offlineCount = $this->checkHeartbeats();
        } catch (Throwable $exception) {
            $this->alertManager->evaluate(
                true,
                'cron',
                'homelab:check-heartbeats',
                self::CRON_SELF_FAILURE_TYPE,
                'critical',
                '"homelab:check-heartbeats" is failing',
                $exception->getMessage(),
                [],
                self::CRON_SELF_FAILURE_THRESHOLD,
            );

            $this->logger->error('Homelab heartbeat check failed', ['message' => $exception->getMessage()]);
            $output->writeln("Heartbeat check failed: {$exception->getMessage()}");

            return Command::FAILURE;
        }

        $this->alertManager->evaluate(
            false,
            'cron',
            'homelab:check-heartbeats',
            self::CRON_SELF_FAILURE_TYPE,
            'critical',
            '"homelab:check-heartbeats" is failing',
            '',
            [],
            self::CRON_SELF_FAILURE_THRESHOLD,
        );

        $this->logger->info('Homelab heartbeat check completed', ['offlineHosts' => $offlineCount]);
        $output->writeln("Checked hosts, {$offlineCount} offline.");

        return Command::SUCCESS;
    }

    private function checkHeartbeats(): int
    {
        $now = new DateTimeImmutable();
        $offlineCount = 0;

        foreach ($this->hostRepository->loadAll() as $host) {
            $secondsSinceUpdate = $now->getTimestamp() - $host->getUpdatedAt()->getTimestamp();
            $isOffline = $secondsSinceUpdate > self::OFFLINE_THRESHOLD_SECONDS;
            $title = "{$host->getName()} is not reporting";
            $description = "No metrics received for {$secondsSinceUpdate} seconds.";

            $transition = $this->alertManager->evaluate(
                $isOffline,
                'homelab',
                $host->getHostId(),
                'host.offline',
                'critical',
                $title,
                $description,
                ['hostId' => $host->getHostId()],
            );

            if ($transition === AlertTransition::Created) {
                $this->eventRepository->insert(
                    Event::create('alert.created', 'critical', $title, $description, null, $host->getHostId()),
                );
            } elseif ($transition === AlertTransition::Resolved) {
                $this->eventRepository->insert(
                    Event::create(
                        'alert.resolved',
                        'info',
                        $title,
                        "Resolved: {$description}",
                        null,
                        $host->getHostId(),
                    ),
                );
            }

            if ($isOffline) {
                $offlineCount++;
            }
        }

        return $offlineCount;
    }
}
