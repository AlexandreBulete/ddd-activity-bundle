<?php

declare(strict_types=1);

use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Admin\Menu\ActivityMenuContributor;
use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Grid\ActivityEntryGrid;
use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Grid\ActivityEntryGridProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Loaded only when `activity.admin.enabled` is true — everything Sylius.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ActivityEntryGridProvider::class);
    $services->set(ActivityEntryGrid::class)->args([param('activity.admin.grid_limits')]);
    $services->set(ActivityMenuContributor::class);
};
