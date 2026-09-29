<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/**
 * Creates the activity journal table.
 *
 * DBAL built-in types only (a migration is a snapshot, ADR 0007); index names
 * generated from the configured table name. A service, to receive
 * `activity.table`.
 */
final class Version20260929120000 extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private readonly string $table,
    ) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return 'Activity: journal table.';
    }

    public function up(Schema $schema): void
    {
        $journal = $schema->createTable($this->table);
        $journal->addColumn('id', Types::GUID);
        $journal->addColumn('occurred_at', Types::DATETIME_IMMUTABLE);
        $journal->addColumn('kind', Types::STRING, ['length' => 10]);
        $journal->addColumn('action', Types::STRING, ['length' => 255]);
        $journal->addColumn('permission', Types::STRING, ['length' => 190, 'notnull' => false]);
        $journal->addColumn('outcome', Types::STRING, ['length' => 10]);
        $journal->addColumn('error', Types::TEXT, ['notnull' => false]);
        $journal->addColumn('actor_kind', Types::STRING, ['length' => 10]);
        $journal->addColumn('actor_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addColumn('actor_label', Types::STRING, ['length' => 255]);
        $journal->addColumn('channel', Types::STRING, ['length' => 32]);
        $journal->addColumn('correlation_id', Types::STRING, ['length' => 64]);
        $journal->addColumn('causation_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addColumn('message_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addColumn('subject_type', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addColumn('subject_id', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addColumn('summary', Types::STRING, ['length' => 190, 'notnull' => false]);
        $journal->addColumn('summary_params', Types::JSONB);
        $journal->addColumn('details', Types::JSONB);
        $journal->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $journal->addIndex(['occurred_at']);
        $journal->addIndex(['correlation_id']);
        $journal->addIndex(['actor_kind', 'actor_id']);
        $journal->addIndex(['subject_type', 'subject_id']);
        $journal->addIndex(['action']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->table);
    }
}
