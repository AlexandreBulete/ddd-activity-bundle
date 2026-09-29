<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Query\FindActivityChain;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;

/**
 * Everything that happened in one chain of causes, oldest first.
 *
 * @implements QueryInterface<list<ActivityEntry>>
 */
final readonly class FindActivityChainQuery implements QueryInterface
{
    public function __construct(
        public string $correlationId,
    ) {}
}
