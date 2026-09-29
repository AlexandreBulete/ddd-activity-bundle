<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration;

use AlexandreBulete\DddActivityBundle\Tests\Integration\App\CreatingIsForbidden;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionCheckerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class AuthorizedTestKernel extends TestKernel
{
    protected function configureAuthorization(ContainerConfigurator $container): void
    {
        $container->services()->set(CreatingIsForbidden::class);
        $container->services()->alias(PermissionCheckerInterface::class, CreatingIsForbidden::class);
    }
}
