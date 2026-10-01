<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Homelab;

use DateTimeImmutable;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'homelab:cleanup',
    description: 'Purges homelab metrics, restarts, health checks and logs past the retention window.',
)]
class CleanupHomelabDataCommand extends Command
{
    /** Default retention window for raw homelab metrics, restarts, health checks and logs. */
    private const DEFAULT_RETENTION_DAYS = 7;

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
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
        $days   = (int) $input->getOption('days');
        $cutoff = new DateTimeImmutable("-{$days} days");

        $purged = [
            'host_metrics'      => $this->hostRepository->purgeMetricsOlderThan($cutoff),
            'container_metrics' => $this->containerRepository->purgeMetricsOlderThan($cutoff),
            'restarts'          => $this->containerRepository->purgeRestartsOlderThan($cutoff),
            'health_checks'     => $this->containerRepository->purgeHealthChecksOlderThan($cutoff),
            'logs'              => $this->containerRepository->purgeLogsOlderThan($cutoff),
        ];

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
