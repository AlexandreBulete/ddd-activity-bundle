<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Query\FindActivityChain;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddFoundation\Application\Query\AsQueryHandler;

#[AsQueryHandler]
final readonly class FindActivityChainHandler
{
    public function __construct(
        private ActivityEntryRepositoryInterface $entries,
    ) {}

    /**
     * @return list<ActivityEntry>
     */
    public function __invoke(FindActivityChainQuery $query): array
    {
        return $this->entries->findChain($query->correlationId);
    }
}
