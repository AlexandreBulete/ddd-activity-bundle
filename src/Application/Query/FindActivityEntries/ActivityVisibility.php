<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries;

/**
 * What a viewer may see of the journal (ADR 0009): the permissions they hold,
 * and who they are — their own actions are always theirs to see.
 */
final readonly class ActivityVisibility
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public array $permissions,
        public ?string $actorId,
    ) {}
}
