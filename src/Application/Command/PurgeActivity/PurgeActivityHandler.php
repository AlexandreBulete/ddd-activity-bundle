<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity;

use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class PurgeActivityHandler
{
    public function __construct(
        private ActivityEntryRepositoryInterface $entries,
    ) {}

    /**
     * @return int<0, max>
     */
    public function __invoke(PurgeActivityCommand $command): int
    {
        return $this->entries->purgeBefore($command->before);
    }
}
