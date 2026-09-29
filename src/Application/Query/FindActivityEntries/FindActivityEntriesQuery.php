<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries;

use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;

/**
 * @implements QueryInterface<ActivityEntryRepositoryInterface>
 */
final readonly class FindActivityEntriesQuery implements QueryInterface
{
    /**
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $withSorting
     * @param ActivityVisibility|null $visibility null: everything (no authorization, or a viewer who holds it all)
     */
    public function __construct(
        public ?int $page = null,
        public ?int $itemsPerPage = null,
        public array $criteria = [],
        public array $withSorting = [],
        public ?ActivityVisibility $visibility = null,
    ) {}
}
