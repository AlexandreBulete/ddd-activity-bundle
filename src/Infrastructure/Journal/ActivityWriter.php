<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Writes journal entries through DBAL, never through the ORM (ADR 0009):
 *
 * - `inTransaction()`: on the application's connection, inside the command's
 *   transaction — a success is recorded if and only if the command commits;
 * - `independently()`: on a connection of its own — a failure survives the
 *   rollback it comes with.
 *
 * The columns are the contract with ActivityEntry.orm.xml.
 */
final readonly class ActivityWriter
{
    public function __construct(
        private Connection $connection,
        private Connection $independentConnection,
        private string $table,
    ) {}

    public function inTransaction(ActivityEntry $entry): void
    {
        $this->insert($this->connection, $entry);
    }

    public function independently(ActivityEntry $entry): void
    {
        $this->insert($this->independentConnection, $entry);
    }

    private function insert(Connection $connection, ActivityEntry $entry): void
    {
        $connection->insert($this->table, [
            'id' => $entry->id->toRfc4122(),
            'occurred_at' => $entry->occurredAt,
            'kind' => $entry->kind->value,
            'action' => $entry->action,
            'permission' => $entry->permission,
            'visible_with' => $entry->visibleWith,
            'outcome' => $entry->outcome->value,
            'error' => $entry->error,
            'actor_kind' => $entry->actorKind,
            'actor_id' => $entry->actorId,
            'actor_label' => $entry->actorLabel,
            'channel' => $entry->channel,
            'correlation_id' => $entry->correlationId,
            'causation_id' => $entry->causationId,
            'message_id' => $entry->messageId,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'summary' => $entry->summary,
            'summary_params' => $entry->summaryParams,
            'details' => $entry->details,
        ], [
            'occurred_at' => Types::DATETIME_IMMUTABLE,
            'summary_params' => Types::JSONB,
            'details' => Types::JSONB,
        ]);
    }
}
