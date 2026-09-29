<?php

declare(strict_types=1);

use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Migrations\Version20260929120000;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Loaded only when DoctrineMigrationsBundle is enabled. Migrations are
 * services (they need `activity.table`); the table name is an explicit
 * argument — DoctrineMigrationsBundle replaces the bindings of tagged
 * migrations.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->tag('doctrine_migrations.migration');

    foreach ([Version20260929120000::class] as $migration) {
        $services->set($migration)->arg('$table', param('activity.table'));
    }
};
