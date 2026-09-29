<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Domain\Model;

use AlexandreBulete\DddActivityBundle\Domain\ValueObject\ActivityEntryId;
use AlexandreBulete\DddActivityBundle\Domain\ValueObject\EntryKind;
use AlexandreBulete\DddActivityBundle\Domain\ValueObject\Outcome;

/**
 * One line of the activity journal: who did what, when, through which chain
 * of causes, and how it ended (ADR 0009).
 *
 * Append-only: no method changes an entry once recorded. The actor is kept as
 * a snapshot (kind, id, label at the time) — the journal must keep saying who
 * acted after the account is renamed or removed.
 */
final class ActivityEntry
{
    /**
     * @param array<string, scalar|null>                   $summaryParams
     * @param array<string, scalar|list<scalar|null>|null> $details
     */
    private function __construct(
        private(set) ActivityEntryId $id,
        private(set) \DateTimeImmutable $occurredAt,
        private(set) EntryKind $kind,
        private(set) string $action,
        private(set) ?string $permission,
        private(set) ?string $visibleWith,
        private(set) Outcome $outcome,
        private(set) ?string $error,
        private(set) string $actorKind,
        private(set) ?string $actorId,
        private(set) string $actorLabel,
        private(set) ?string $actorCredential,
        private(set) string $channel,
        private(set) string $correlationId,
        private(set) ?string $causationId,
        private(set) ?string $messageId,
        private(set) ?string $subjectType,
        private(set) ?string $subjectId,
        private(set) ?string $summary,
        private(set) array $summaryParams,
        private(set) array $details,
    ) {}

    /**
     * @param array<string, scalar|null>                   $summaryParams
     * @param array<string, scalar|list<scalar|null>|null> $details
     */
    public static function record(
        ActivityEntryId $id,
        \DateTimeImmutable $occurredAt,
        EntryKind $kind,
        string $action,
        Outcome $outcome,
        string $actorKind,
        ?string $actorId,
        string $actorLabel,
        string $channel,
        string $correlationId,
        ?string $causationId = null,
        ?string $messageId = null,
        ?string $actorCredential = null,
        ?string $error = null,
        ?string $permission = null,
        ?string $visibleWith = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $summary = null,
        array $summaryParams = [],
        array $details = [],
    ): self {
        if ($action === '' || $actorLabel === '' || $channel === '' || $correlationId === '') {
            throw new \InvalidArgumentException('An activity entry needs an action, an actor, a channel and a chain.');
        }
        if (($outcome === Outcome::Succeeded) !== ($error === null)) {
            throw new \InvalidArgumentException('An error is recorded exactly when the outcome is not a success.');
        }
        if (($subjectType === null) !== ($subjectId === null)) {
            throw new \InvalidArgumentException('A subject needs both a type and an id.');
        }

        return new self(
            $id, $occurredAt, $kind, $action, $permission, $visibleWith, $outcome, $error,
            $actorKind, $actorId, $actorLabel, $actorCredential, $channel, $correlationId, $causationId, $messageId,
            $subjectType, $subjectId, $summary, $summaryParams, $details,
        );
    }
}
