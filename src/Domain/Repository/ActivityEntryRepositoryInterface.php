<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Domain\Repository;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddFoundation\Domain\Repository\RepositoryInterface;

/**
 * Read side of the journal, plus retention. Entries are written by the
 * journal itself, never through this repository: nothing here adds, changes
 * or removes a single entry.
 *
 * @extends RepositoryInterface<ActivityEntry>
 */
interface ActivityEntryRepositoryInterface extends RepositoryInterface
{
    /**
     * Every entry of one chain, oldest first.
     *
     * @return list<ActivityEntry>
     */
    public function findChain(string $correlationId): array;

    /**
     * Removes entries older than $before — the retention policy, never a
     * way to edit history.
     *
     * @return int<0, max> how many were removed
     */
    public function purgeBefore(\DateTimeImmutable $before): int;
}
