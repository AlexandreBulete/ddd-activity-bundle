<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle;

use AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Type\ActivityEntryIdType;
use AlexandreBulete\DddActivityBundle\Infrastructure\Symfony\DependencyInjection\JournalMiddlewarePass;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The activity journal (ADR 0009): who did what, through which chain of
 * causes, and how it ended — for every command, every #[Journaled] query and
 * every external effect an adapter records.
 *
 * Relies on ddd-symfony-bundle's tracing for the actor and the chain; works
 * without Symfony Security and without an IAM.
 *
 * @phpstan-type ActivityConfig array{
 *     table: non-empty-string,
 *     retention_days: positive-int,
 *     admin: array{enabled: bool, grid_limits: list<int>},
 * }
 */
final class DddActivityBundle extends AbstractBundle
{
    protected string $extensionAlias = 'activity';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('table')
                    ->defaultValue('activity_log')
                    ->cannotBeEmpty()
                    ->info('Table of the journal.')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(730)
                    ->min(1)
                    ->info('Entries older than this are removed by `activity:purge`.')
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Sylius back-office screen. Turn off for a headless deployment.')
                        ->end()
                        ->arrayNode('grid_limits')
                            ->integerPrototype()->end()
                            ->defaultValue([25, 50, 100])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Validated and defaulted by the tree in configure().
        /** @var ActivityConfig $config */
        $container->parameters()
            ->set('activity.table', $config['table'])
            ->set('activity.retention_days', $config['retention_days'])
            ->set('activity.admin.grid_limits', $config['admin']['grid_limits']);

        $container->import($this->getPath() . '/config/services.php');

        if (self::migrationsEnabled($builder)) {
            $container->import($this->getPath() . '/config/services_migrations.php');
        }

        if ($config['admin']['enabled']) {
            $container->import($this->getPath() . '/config/services_admin.php');
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [ActivityEntryIdType::NAME => ActivityEntryIdType::class],
            ],
            'orm' => [
                'mappings' => [
                    'Activity' => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => $this->getPath() . '/src/Infrastructure/Doctrine/Mapping',
                        'prefix' => 'AlexandreBulete\DddActivityBundle\Domain\Model',
                        'alias' => 'Activity',
                    ],
                ],
            ],
        ]);

        // The migration is a service (it needs `activity.table`), which
        // DoctrineMigrationsBundle only looks up with this switch on.
        if (self::migrationsEnabled($builder)) {
            $builder->prependExtensionConfig('doctrine_migrations', ['enable_service_migrations' => true]);
        }

        $builder->prependExtensionConfig('framework', [
            'translator' => ['paths' => [$this->getPath() . '/translations']],
        ]);

        if (self::adminEnabled($builder)) {
            $builder->prependExtensionConfig('sylius_resource', [
                'mapping' => ['paths' => [$this->getPath() . '/src/Infrastructure/Sylius/Resource']],
            ]);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Before Messenger's pass, which builds the buses from these parameters.
        $container->addCompilerPass(new JournalMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }

    /**
     * From `kernel.bundles`: inside loadExtension() the builder only knows
     * this extension, so hasExtension() would always answer false.
     */
    private static function migrationsEnabled(ContainerBuilder $builder): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->getParameter('kernel.bundles');

        return in_array(DoctrineMigrationsBundle::class, $bundles, true);
    }

    /**
     * Read before the config tree is processed: raw values, own fallback.
     */
    private static function adminEnabled(ContainerBuilder $builder): bool
    {
        $enabled = true;
        foreach ($builder->getExtensionConfig('activity') as $config) {
            $admin = $config['admin'] ?? null;
            if (is_array($admin) && is_bool($admin['enabled'] ?? null)) {
                $enabled = $admin['enabled'];
            }
        }

        return $enabled;
    }
}
