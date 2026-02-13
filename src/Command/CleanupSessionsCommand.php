<?php

declare(strict_types=1);

namespace LukaLtaApi\Command;

use LukaLtaApi\Repository\SessionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupSessionsCommand extends Command
{
    public const string COMMAND_NAME = 'sessions:cleanup';

    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->sessionRepository->cleanupSessions();
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
        }

        $output->writeln('<info>Sessions cleanup successfully.</info>');

        return Command::SUCCESS;
    }
}
