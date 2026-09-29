<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration;

use AlexandreBulete\DddActivityBundle\DddActivityBundle;
use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddFoundation\Application\Query\QueryBusInterface;
use AlexandreBulete\DddSymfonyBundle\DddSymfonyBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The journal as a project installs it, minus the back office: no Sylius, no
 * Security, no IAM. On the database given by DDD_TEST_DATABASE_URL, or a
 * SQLite file (a second connection must see the same database).
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new DddSymfonyBundle();
        yield new DddActivityBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ddd-activity-bundle-tests/cache/' . (new \ReflectionClass($this))->getShortName();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ddd-activity-bundle-tests/log';
    }

    /**
     * No permission checker by default: authorization stays off.
     */
    protected function configureAuthorization(ContainerConfigurator $container): void
    {
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $url = getenv('DDD_TEST_DATABASE_URL');

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
        ]);
        $container->extension('doctrine', [
            'dbal' => [
                'url' => is_string($url) && $url !== '' ? $url : 'sqlite:///' . sys_get_temp_dir() . '/ddd-activity-bundle-tests/journal.db',
                // Only the tables of this test: a shared test database may hold others.
                'schema_filter' => '~^activity_~',
            ],
            'orm' => [
                'mappings' => [
                    'TestApp' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/App',
                        'prefix' => 'AlexandreBulete\DddActivityBundle\Tests\Integration\App',
                    ],
                ],
            ],
        ]);
        $container->extension('activity', [
            'table' => 'activity_test_log',
            'admin' => ['enabled' => false],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->load('AlexandreBulete\DddActivityBundle\Tests\Integration\App\\', __DIR__ . '/App/*Handler.php');

        $services->alias('test.command_bus', CommandBusInterface::class)->public();
        $services->alias('test.query_bus', QueryBusInterface::class)->public();

        // Some tests break the journal on purpose: what it logs then is expected.
        $services->set('logger', NullLogger::class);

        $this->configureAuthorization($container);
    }
}
