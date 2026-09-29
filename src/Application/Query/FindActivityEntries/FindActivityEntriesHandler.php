<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddFoundation\Application\Handler\QueryCollectionHandler;
use AlexandreBulete\DddFoundation\Application\Query\AsQueryHandler;
use AlexandreBulete\DddFoundation\Domain\Repository\RepositoryInterface;

/**
 * @extends QueryCollectionHandler<ActivityEntry>
 */
#[AsQueryHandler]
final readonly class FindActivityEntriesHandler extends QueryCollectionHandler
{
    public function __construct(ActivityEntryRepositoryInterface $entries)
    {
        parent::__construct($entries);
    }

    /**
     * @return RepositoryInterface<ActivityEntry>
     */
    public function __invoke(FindActivityEntriesQuery $query): RepositoryInterface
    {
        return $this->build($query);
    }
}
