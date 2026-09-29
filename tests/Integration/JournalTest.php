<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration;

use AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity\PurgeActivityCommand;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20260929120000;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20261001130000;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20261002120000;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\CreateThing;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\Housekeeping;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\ListThings;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\ReadContract;
use AlexandreBulete\DddActivityBundle\Tests\Integration\App\Thing;
use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddFoundation\Application\Query\QueryBusInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Actor;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JournalTest extends KernelTestCase
{
    private const TABLE = 'activity_test_log';

    private Connection $connection;
    private CommandBusInterface $commands;
    private QueryBusInterface $queries;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
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
        foreach ([Version20260929120000::class, Version20261001130000::class, Version20261002120000::class] as $migration) {
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
    public function the_migration_creates_exactly_the_mapped_table(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        self::assertSame([], (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata()));
    }

    #[Test]
    public function a_command_is_journaled_with_who_what_and_its_description(): void
    {
        $this->commands->dispatch(new CreateThing('alpha'));

        $entry = $this->only(CreateThing::class);
        self::assertSame('succeeded', $entry['outcome']);
        self::assertSame('command', $entry['kind']);
        self::assertSame('system', $entry['actor_kind']);
        self::assertSame('thing', $entry['subject_type']);
        self::assertSame('alpha', $entry['subject_id']);
        self::assertSame('thing.created', $entry['summary']);
        self::assertSame(['name' => 'alpha'], $this->json($entry['details']));
        self::assertSame($entry['message_id'], $entry['correlation_id'], 'the first message starts the chain');
        self::assertSame('thing.create', $entry['permission']);
    }

    #[Test]
    public function an_agent_is_journaled_with_the_token_it_used(): void
    {
        $context = self::getContainer()->get(TraceContext::class);
        self::assertInstanceOf(TraceContext::class, $context);

        $context->runAs(Actor::agent('a-7', 'Veille', 'tok-1'), 'api', fn () => $this->commands->dispatch(new CreateThing('alpha')));

        $entry = $this->only(CreateThing::class);
        self::assertSame('agent', $entry['actor_kind']);
        self::assertSame('a-7', $entry['actor_id']);
        self::assertSame('tok-1', $entry['actor_credential']);
        self::assertSame('api', $entry['channel']);
    }

    #[Test]
    public function a_failure_is_journaled_although_its_transaction_is_rolled_back(): void
    {
        try {
            $this->commands->dispatch(new CreateThing('beta', thenFail: true));
            self::fail('the domain refusal must surface');
        } catch (\DomainException) {
        }

        $entry = $this->only(CreateThing::class);
        self::assertSame('failed', $entry['outcome']);
        self::assertSame('DomainException: Refused by the domain.', $entry['error']);
        self::assertSame(0, $this->rowsIn('activity_test_thing'), 'the command itself was rolled back');
    }

    #[Test]
    public function a_failure_at_flush_time_is_a_failure_too(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('SQLite has a single writer: once the command wrote, its failure cannot be journaled — it is logged instead (see IndependentConnectionFactory).');
        }

        $this->commands->dispatch(new CreateThing('gamma'));

        try {
            $this->commands->dispatch(new CreateThing('gamma'));
            self::fail('the unique constraint must surface');
        } catch (\Throwable) {
        }

        $outcomes = array_column($this->entries(CreateThing::class), 'outcome');
        self::assertSame(['succeeded', 'failed'], $outcomes);
    }

    #[Test]
    public function what_a_command_triggers_is_journaled_in_the_same_chain(): void
    {
        $this->commands->dispatch(new CreateThing('delta', thenCreate: true));

        $byId = array_column($this->entries(CreateThing::class), null, 'subject_id');
        self::assertCount(2, $byId);
        self::assertSame($byId['delta']['correlation_id'], $byId['delta-child']['correlation_id']);
        self::assertSame($byId['delta']['message_id'], $byId['delta-child']['causation_id']);
    }

    #[Test]
    public function journaling_a_failure_never_hides_it(): void
    {
        $this->connection->createSchemaManager()->dropTable(self::TABLE);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Refused by the domain.');

        $this->commands->dispatch(new CreateThing('theta', thenFail: true));
    }

    #[Test]
    public function an_external_effect_is_journaled_in_the_chain_that_caused_it(): void
    {
        $this->commands->dispatch(new CreateThing('epsilon'));

        $effect = $this->only('thing.announced');
        $command = $this->only(CreateThing::class);
        self::assertSame('effect', $effect['kind']);
        self::assertSame($command['correlation_id'], $effect['correlation_id']);
        self::assertSame('thing.create', $effect['permission'], 'visible to whoever sees its cause');
    }

    #[Test]
    public function an_effect_stays_journaled_when_its_command_fails(): void
    {
        try {
            $this->commands->dispatch(new CreateThing('zeta', thenFail: true));
        } catch (\DomainException) {
        }

        self::assertSame('succeeded', $this->only('thing.announced')['outcome'], 'it did happen');
    }

    #[Test]
    public function what_is_marked_otherwise_is_left_out(): void
    {
        $this->commands->dispatch(new Housekeeping());
        $this->queries->ask(new ListThings());
        $this->queries->ask(new ReadContract());

        self::assertSame([], $this->entries(Housekeeping::class));
        self::assertSame([], $this->entries(ListThings::class));
        self::assertSame('query', $this->only(ReadContract::class)['kind']);
    }

    #[Test]
    public function purging_removes_old_entries_and_journals_itself(): void
    {
        $this->commands->dispatch(new CreateThing('eta'));
        $this->connection->executeStatement(sprintf("UPDATE %s SET occurred_at = '2020-01-01 00:00:00'", self::TABLE));

        $removed = $this->commands->dispatch(new PurgeActivityCommand(new \DateTimeImmutable('2021-01-01')));

        self::assertSame(2, $removed, 'the command and its effect');
        self::assertSame('succeeded', $this->only(PurgeActivityCommand::class)['outcome']);
        self::assertSame(1, $this->rowsIn(self::TABLE));
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

    /**
     * @return array<mixed>
     */
    private function json(mixed $column): array
    {
        self::assertIsString($column);
        $decoded = json_decode($column, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
