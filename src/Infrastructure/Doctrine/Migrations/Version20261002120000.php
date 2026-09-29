<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/**
 * The credential an actor used (ADR 0011): the id of an API token, so that
 * everything a leaked token did is one filter away. Additive only: a nullable
 * column and an index.
 */
final class Version20261002120000 extends AbstractMigration
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
        return 'Activity: the credential an actor used (an API token id).';
    }

    public function up(Schema $schema): void
    {
        $journal = $schema->getTable($this->table);
        $journal->addColumn('actor_credential', Types::STRING, ['length' => 64, 'notnull' => false]);
        $journal->addIndex(['actor_credential']);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable($this->table)->dropColumn('actor_credential');
    }
}
