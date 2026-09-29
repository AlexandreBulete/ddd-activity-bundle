<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class HousekeepingHandler
{
    public function __invoke(Housekeeping $command): void
    {
    }
}
