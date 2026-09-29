<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Query\AsQueryHandler;

#[AsQueryHandler]
final readonly class ReadContractHandler
{
    public function __invoke(ReadContract $query): string
    {
        return 'contract';
    }
}
