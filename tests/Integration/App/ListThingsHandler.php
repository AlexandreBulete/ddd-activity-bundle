<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Query\AsQueryHandler;

#[AsQueryHandler]
final readonly class ListThingsHandler
{
    public function __invoke(ListThings $query): string
    {
        return 'things';
    }
}
