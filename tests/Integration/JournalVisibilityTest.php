<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration;

use AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity\PurgeActivityCommand;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20260929120000;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20261001130000;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\CreateThing;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\Housekeeping;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\ListThings;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\ReadContract;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\Thing;
use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddFoundation\Application\Query\QueryBusInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries\ActivityVisibility;
use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries\FindActivityEntriesQuery;
use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionDenied;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Actor;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceContext;

/**
 * The journal with authorization on: refusals are journaled, and a viewer
 * only sees what their permissions allow.
 */
final class JournalVisibilityTest extends KernelTestCase
{
    private const TABLE = 'activity_test_log';

    private Connection $connection;
    private CommandBusInterface $commands;
    private QueryBusInterface $queries;

    protected static function getKernelClass(): string
    {
        return AuthorizedTestKernel::class;
    }

    protected function setUp(): void
    {
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->connection = $em->getConnection();

        $commands = $container->get('test.command_bus');
        self::assertInstanceOf(CommandBusInterface::class, $commands);
        $this->commands = $commands;
        $queries = $container->get('test.query_bus');
        self::assertInstanceOf(QueryBusInterface::class, $queries);
        $this->queries = $queries;

        // Fresh tables: the journal from the bundle's own migration, the test
        // entity from its mapping.
        $schemaManager = $this->connection->createSchemaManager();
        foreach ($schemaManager->listTableNames() as $table) {
            $schemaManager->dropTable($table);
        }
        foreach ([Version20260929120000::class, Version20261001130000::class] as $migration) {
            $from = $schemaManager->introspectSchema();
            $to = clone $from;
            (new $migration($this->connection, new NullLogger(), self::TABLE))->up($to);
            foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($from, $to)) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Thing::class)]);
    }

    #[Test]
    public function a_refusal_is_journaled_as_refused_with_who_asked(): void
    {
        try {
            $this->as(Actor::user('u-1', 'Pauline'), fn () => $this->commands->dispatch(new CreateThing('alpha')));
            self::fail('refused expected');
        } catch (PermissionDenied) {
        }

        $entry = $this->only(CreateThing::class);
        self::assertSame('refused', $entry['outcome']);
        self::assertSame('Pauline', $entry['actor_label']);
        self::assertSame(0, $this->rowsIn('activity_test_thing'));
    }

    #[Test]
    public function a_viewer_sees_what_they_may_see_and_their_own_actions(): void
    {
        $this->commands->dispatch(new CreateThing('by-system'));             // thing.create, by the system
        $this->as(Actor::user('u-1', 'Pauline'), fn () => $this->queries->ask(new ReadContract())); // thing.read_contract, by Pauline

        $seen = fn (ActivityVisibility $visibility): array => $this->actionsVisibleTo($visibility);

        self::assertSame([ReadContract::class], $seen(new ActivityVisibility(['thing.read_contract'], null)));
        self::assertSame([CreateThing::class, 'thing.announced'], $seen(new ActivityVisibility(['thing.create'], null)), 'an effect follows its cause');
        self::assertSame([ReadContract::class], $seen(new ActivityVisibility([], 'u-1')), 'one\'s own actions');
        self::assertSame([], $seen(new ActivityVisibility([], 'nobody')));
    }

    /**
     * @return list<string>
     */
    private function actionsVisibleTo(ActivityVisibility $visibility): array
    {
        $actions = [];
        foreach ($this->queries->ask(new FindActivityEntriesQuery(visibility: $visibility, withSorting: ['action' => 'asc'])) as $entry) {
            self::assertInstanceOf(ActivityEntry::class, $entry);
            $actions[] = $entry->action;
        }

        return $actions;
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function as(Actor $actor, callable $operation): mixed
    {
        $context = self::getContainer()->get(TraceContext::class);
        self::assertInstanceOf(TraceContext::class, $context);

        return $context->runAs($actor, 'test', $operation);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(string $action): array
    {
        /** @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE action = ? ORDER BY occurred_at, id', self::TABLE),
            [$action],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function only(string $action): array
    {
        $entries = $this->entries($action);
        self::assertCount(1, $entries, $action);

        return $entries[0];
    }

    private function rowsIn(string $table): int
    {
        $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
        self::assertIsNumeric($count);

        return (int) $count;
    }

}
