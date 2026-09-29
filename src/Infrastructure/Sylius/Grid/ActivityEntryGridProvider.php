<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Grid;

use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries\ActivityVisibility;
use AlexandreBulete\DddActivityBundle\Application\Query\FindActivityEntries\FindActivityEntriesQuery;
use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Resource\ActivityEntryResource;
use AlexandreBulete\DddFoundation\Application\Query\QueryBusInterface;
use AlexandreBulete\DddSyliusBundle\Grid\GridPageResolver;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionCheckerInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionRegistry;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\ActorResolverInterface;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Sylius\Component\Grid\Data\DataProviderInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Query bus in, DTOs out. The summary is translated here: the journal stores a
 * key and its parameters, so it reads in the viewer's language.
 *
 * The viewer sees what their permissions allow (ADR 0009) — decided here,
 * where the viewer is known, and applied by the query.
 */
final readonly class ActivityEntryGridProvider implements DataProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private TranslatorInterface $translator,
        private PermissionRegistry $permissions,
        private ActorResolverInterface $actors,
        private ?PermissionCheckerInterface $checker = null,
    ) {}

    /**
     * @return PagerfantaInterface<ActivityEntryResource>
     */
    public function getData(Grid $grid, Parameters $parameters): PagerfantaInterface
    {
        /** @var array<string, mixed> $criteria */
        $criteria = $parameters->get('criteria', []);

        /** @var array<string, string> $sorting */
        $sorting = $parameters->get('sorting', $grid->getSorting());

        $entries = $this->queryBus->ask(new FindActivityEntriesQuery(
            page: GridPageResolver::getCurrentPage($grid, $parameters),
            itemsPerPage: GridPageResolver::getItemsPerPage($grid, $parameters),
            criteria: $criteria,
            withSorting: $sorting,
            visibility: $this->visibility(),
        ));

        $data = [];
        foreach ($entries as $entry) {
            $data[] = ActivityEntryResource::fromModel($entry, $this->summaryOf($entry));
        }

        return new Pagerfanta(new FixedAdapter($entries->count(), $data));
    }

    /**
     * Null — everything — without authorization, or for a viewer who holds
     * every permission.
     */
    private function visibility(): ?ActivityVisibility
    {
        $actor = $this->actors->resolve();
        if ($this->checker === null || $actor === null || $actor->isSystem()) {
            return null;
        }

        $all = $this->permissions->all();
        $held = array_values(array_filter($all, fn (string $permission): bool => $this->checker->isGranted($actor, $permission)));

        return count($held) === count($all) ? null : new ActivityVisibility($held, $actor->id);
    }

    private function summaryOf(ActivityEntry $entry): string
    {
        if ($entry->summary === null) {
            return '';
        }

        $parameters = [];
        foreach ($entry->summaryParams as $name => $value) {
            $parameters['%' . $name . '%'] = $value;
        }

        return $this->translator->trans($entry->summary, $parameters);
    }
}
