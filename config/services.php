<?php

declare(strict_types=1);

use AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity\PurgeActivityHandler;
use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityChain\FindActivityChainHandler;
use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries\FindActivityEntriesHandler;
use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\DoctrineActivityEntryRepository;
use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Listener\TableNameListener;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\ActivityJournal;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\ActivityWriter;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\EntryFactory;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\JournalContext;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\IndependentConnectionFactory;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\JournalMiddleware;
use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\JournalPolicy;
use AlexandreBulete\DddActivityBundle\Infrastructure\Symfony\Command\PurgeActivityConsoleCommand;
use AlexandreBulete\DddActivityBundle\Infrastructure\Symfony\DependencyInjection\JournalMiddlewarePass;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // ── Read side ────────────────────────────────────────────────────────────
    $services->set(DoctrineActivityEntryRepository::class);
    $services->alias(ActivityEntryRepositoryInterface::class, DoctrineActivityEntryRepository::class);

    $services->set(FindActivityEntriesHandler::class);
    $services->set(FindActivityChainHandler::class);
    $services->set(PurgeActivityHandler::class);

    $services->set(TableNameListener::class)
        ->args([param('activity.table')])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    // ── Write side: DBAL, two connections (see ActivityWriter) ──────────────
    $services->set('ddd_activity.independent_connection', Connection::class)
        ->factory([IndependentConnectionFactory::class, 'create'])
        ->args([service('doctrine.dbal.default_connection')]);

    $services->set(ActivityWriter::class)
        ->args([
            service('doctrine.dbal.default_connection'),
            service('ddd_activity.independent_connection'),
            param('activity.table'),
        ]);

    $services->set(JournalPolicy::class);
    $services->set(JournalContext::class);
    $services->set(EntryFactory::class);
    $services->set(ActivityJournal::class)->public();

    // Placed in the buses by JournalMiddlewarePass.
    $services->set(JournalMiddlewarePass::MIDDLEWARE, JournalMiddleware::class);

    $services->set(PurgeActivityConsoleCommand::class)
        ->arg('$retentionDays', param('activity.retention_days'));
};
