<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Alert;

use DateTimeImmutable;
use LukaLtaApi\Repository\AlertFailureCounterRepository;
use LukaLtaApi\Repository\AlertRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'alerts:cleanup',
    description: 'Purges resolved alerts and stale debounce counters past the retention window.',
)]
class CleanupAlertsCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS_RESOLVED = 30;
    private const DEFAULT_RETENTION_DAYS_COUNTERS = 7;

    public function __construct(
        private readonly AlertRepository               $alertRepository,
        private readonly AlertFailureCounterRepository  $counterRepository,
        private readonly LoggerInterface                $logger,
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
                'Retention window in days for resolved alerts.',
                (string) self::DEFAULT_RETENTION_DAYS_RESOLVED,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $resolvedCutoff = new DateTimeImmutable("-{$days} days");
        $counterCutoff = new DateTimeImmutable('-' . self::DEFAULT_RETENTION_DAYS_COUNTERS . ' days');

        $purged = [
            'resolved_alerts' => $this->alertRepository->purgeResolvedOlderThan($resolvedCutoff),
            'failure_counters' => $this->counterRepository->purgeStaleOlderThan($counterCutoff),
        ];

        $this->logger->info('Alert cleanup completed', [
            'cutoff' => $resolvedCutoff->format('Y-m-d H:i:s'),
            'purged' => $purged,
        ]);

        foreach ($purged as $table => $count) {
            $output->writeln("Purged {$count} rows from {$table}.");
        }

        return Command::SUCCESS;
    }
}
