<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/**
 * Visibility by permission (ADR 0009): entries carry the permission of their
 * action (already a column), and an optional wider one they are visible with.
 * Additive only: a nullable column and an index.
 */
final class Version20261001130000 extends AbstractMigration
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
        return 'Activity: visibility by permission.';
    }

    public function up(Schema $schema): void
    {
        $journal = $schema->getTable($this->table);
        $journal->addColumn('visible_with', Types::STRING, ['length' => 190, 'notnull' => false]);
        $journal->addIndex(['permission']);
    }

    public function down(Schema $schema): void
    {
        $journal = $schema->getTable($this->table);
        $journal->dropColumn('visible_with');
    }
}
