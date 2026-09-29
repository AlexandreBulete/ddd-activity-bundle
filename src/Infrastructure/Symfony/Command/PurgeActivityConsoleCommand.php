<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Symfony\Command;

use AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity\PurgeActivityCommand;
use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'activity:purge',
    description: 'Remove journal entries older than the retention period (activity.retention_days).',
)]
final class PurgeActivityConsoleCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClockInterface $clock,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $before = $this->clock->now()->modify(sprintf('-%d days', $this->retentionDays));

        $removed = $this->commandBus->dispatch(new PurgeActivityCommand($before));

        (new SymfonyStyle($input, $output))->success(sprintf(
            '%d entr%s older than %s removed.',
            $removed,
            $removed === 1 ? 'y' : 'ies',
            $before->format('Y-m-d'),
        ));

        return Command::SUCCESS;
    }
}
