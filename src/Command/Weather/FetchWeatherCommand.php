<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Weather;

use LukaLtaApi\Api\Weather\Service\WeatherService;
use LukaLtaApi\Service\AlertManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'weather:fetch',
    description: 'Fetches current conditions and forecast from the configured weather provider.',
)]
class FetchWeatherCommand extends Command
{
    private const string CRON_SELF_FAILURE_TYPE = 'cron.failed';
    private const int CRON_SELF_FAILURE_THRESHOLD = 2;

    public function __construct(
        private readonly WeatherService  $service,
        private readonly AlertManager    $alertManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->service->refresh();
        } catch (Throwable $exception) {
            $this->alertManager->evaluate(
                true,
                'cron',
                'weather:fetch',
                self::CRON_SELF_FAILURE_TYPE,
                'critical',
                '"weather:fetch" is failing',
                $exception->getMessage(),
                [],
                self::CRON_SELF_FAILURE_THRESHOLD,
            );

            $this->logger->error('Weather fetch failed', [
                'message' => $exception->getMessage(),
                'cause' => $exception->getPrevious()?->getMessage(),
            ]);
            $output->writeln("Weather fetch failed: {$exception->getMessage()}");
            if ($exception->getPrevious() !== null) {
                $output->writeln("Cause: {$exception->getPrevious()->getMessage()}");
            }

            return Command::FAILURE;
        }

        $this->alertManager->evaluate(
            false,
            'cron',
            'weather:fetch',
            self::CRON_SELF_FAILURE_TYPE,
            'critical',
            '"weather:fetch" is failing',
            '',
            [],
            self::CRON_SELF_FAILURE_THRESHOLD,
        );

        $output->writeln('Weather data refreshed.');

        return Command::SUCCESS;
    }
}
