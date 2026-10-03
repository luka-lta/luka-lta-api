<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Homelab;

use DateTimeImmutable;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabEventRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Service\AlertManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'homelab:cleanup',
    description: 'Purges homelab metrics, restarts, health checks and logs past the retention window.',
)]
class CleanupHomelabDataCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 7;
    private const string CRON_SELF_FAILURE_TYPE = 'cron.failed';
    private const int CRON_SELF_FAILURE_THRESHOLD = 2;

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly HomelabEventRepository     $eventRepository,
        private readonly AlertManager               $alertManager,
        private readonly LoggerInterface             $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Retention window in days.',
                (string) self::DEFAULT_RETENTION_DAYS,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $cutoff = new DateTimeImmutable("-{$days} days");

        try {
            $purged = [
                'host_metrics' => $this->hostRepository->purgeMetricsOlderThan($cutoff),
                'container_metrics' => $this->containerRepository->purgeMetricsOlderThan($cutoff),
                'restarts' => $this->containerRepository->purgeRestartsOlderThan($cutoff),
                'health_checks' => $this->containerRepository->purgeHealthChecksOlderThan($cutoff),
                'logs' => $this->containerRepository->purgeLogsOlderThan($cutoff),
                'events' => $this->eventRepository->purgeOlderThan($cutoff),
            ];
        } catch (Throwable $exception) {
            $this->alertManager->evaluate(
                true,
                'cron',
                'homelab:cleanup',
                self::CRON_SELF_FAILURE_TYPE,
                'critical',
                '"homelab:cleanup" is failing',
                $exception->getMessage(),
                [],
                self::CRON_SELF_FAILURE_THRESHOLD,
            );

            $this->logger->error('Homelab data cleanup failed', ['message' => $exception->getMessage()]);
            $output->writeln("Homelab cleanup failed: {$exception->getMessage()}");

            return Command::FAILURE;
        }

        $this->alertManager->evaluate(
            false,
            'cron',
            'homelab:cleanup',
            self::CRON_SELF_FAILURE_TYPE,
            'critical',
            '"homelab:cleanup" is failing',
            '',
            [],
            self::CRON_SELF_FAILURE_THRESHOLD,
        );

        $this->logger->info('Homelab data cleanup completed', [
            'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            'purged' => $purged,
        ]);

        foreach ($purged as $table => $count) {
            $output->writeln("Purged {$count} rows from {$table}.");
        }

        return Command::SUCCESS;
    }
}
