<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * A second connection to the same database, outside the application's
 * transaction.
 *
 * A failure must be journaled although the command's transaction is rolled
 * back — and after a database error, the ORM's EntityManager may be closed.
 * Writing through this connection survives both.
 *
 * SQLite has a single writer: once the command's transaction has written, this
 * connection cannot. It is opened without any busy timeout, so that such a
 * write fails at once — never makes the application wait — and the journal
 * logs what it could not record (see JournalMiddleware). Full guarantees need
 * PostgreSQL or MySQL. SQLite in memory gets the main connection: a second
 * one would open another, empty database.
 */
final class IndependentConnectionFactory
{
    public static function create(Connection $main): Connection
    {
        $params = $main->getParams();

        if ($main->getDatabasePlatform() instanceof SQLitePlatform) {
            if (($params['memory'] ?? false) === true) {
                return $main;
            }

            $params['driverOptions'] = [\PDO::ATTR_TIMEOUT => 0] + ($params['driverOptions'] ?? []);
        }

        return DriverManager::getConnection($params, $main->getConfiguration());
    }
}
