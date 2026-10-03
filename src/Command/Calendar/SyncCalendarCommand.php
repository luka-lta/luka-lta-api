<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Calendar;

use LukaLtaApi\Api\Calendar\Service\CalendarService;
use LukaLtaApi\Service\AlertManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'calendar:sync',
    description: 'Re-syncs events from every enabled calendar source.',
)]
class SyncCalendarCommand extends Command
{
    private const string CRON_SELF_FAILURE_TYPE = 'cron.failed';
    private const int CRON_SELF_FAILURE_THRESHOLD = 2;

    public function __construct(
        private readonly CalendarService $service,
        private readonly AlertManager    $alertManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->service->syncAll();
        } catch (Throwable $exception) {
            $this->alertManager->evaluate(
                true,
                'cron',
                'calendar:sync',
                self::CRON_SELF_FAILURE_TYPE,
                'critical',
                '"calendar:sync" is failing',
                $exception->getMessage(),
                [],
                self::CRON_SELF_FAILURE_THRESHOLD,
            );

            $this->logger->error('Calendar sync failed', [
                'message' => $exception->getMessage(),
                'cause' => $exception->getPrevious()?->getMessage(),
            ]);
            $output->writeln("Calendar sync failed: {$exception->getMessage()}");
            if ($exception->getPrevious() !== null) {
                $output->writeln("Cause: {$exception->getPrevious()->getMessage()}");
            }

            return Command::FAILURE;
        }

        $this->alertManager->evaluate(
            false,
            'cron',
            'calendar:sync',
            self::CRON_SELF_FAILURE_TYPE,
            'critical',
            '"calendar:sync" is failing',
            '',
            [],
            self::CRON_SELF_FAILURE_THRESHOLD,
        );

        $output->writeln('Calendar events synced.');

        return Command::SUCCESS;
    }
}
