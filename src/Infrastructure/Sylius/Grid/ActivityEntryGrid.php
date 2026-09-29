<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Grid;

use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Resource\ActivityEntryResource;
use Sylius\Bundle\GridBundle\Builder\Field\DateTimeField;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

/**
 * No action group: read-only, and that absence is the enforcement.
 *
 * "Chain" filters the journal on one correlation id — everything that
 * followed from the same entry point, in order.
 */
class ActivityEntryGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    /**
     * @param list<int> $limits
     */
    public function __construct(
        private readonly array $limits,
    ) {}

    public static function getName(): string
    {
        return self::class;
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->setProvider(ActivityEntryGridProvider::class)
            ->setLimits($this->limits)
            ->orderBy('occurredAt', 'desc')
            ->addFilter(StringFilter::create('actorLabel', ['actor_label'])->setLabel('activity.field.actor'))
            ->addFilter(StringFilter::create('actorCredential', ['actor_credential'])->setLabel('activity.field.credential'))
            ->addFilter(StringFilter::create('action', ['action'])->setLabel('activity.field.action'))
            ->addFilter(StringFilter::create('subjectType', ['subject_type'])->setLabel('activity.field.subject_type'))
            ->addFilter(StringFilter::create('subjectId', ['subject_id'])->setLabel('activity.field.subject_id'))
            ->addFilter(StringFilter::create('outcome', ['outcome'])->setLabel('activity.field.outcome'))
            ->addFilter(StringFilter::create('correlationId', ['correlation_id'])->setLabel('activity.field.chain'))
            ->addField(DateTimeField::create('occurredAt')->setLabel('activity.field.occurred_at')->setSortable(true))
            ->addField(StringField::create('actor')->setLabel('activity.field.actor'))
            ->addField(StringField::create('credential')->setLabel('activity.field.credential'))
            ->addField(StringField::create('action')->setLabel('activity.field.action'))
            ->addField(StringField::create('summary')->setLabel('activity.field.summary'))
            ->addField(StringField::create('subject')->setLabel('activity.field.subject'))
            ->addField(StringField::create('outcome')->setLabel('activity.field.outcome'))
            ->addField(StringField::create('channel')->setLabel('activity.field.channel'))
            ->addField(StringField::create('error')->setLabel('activity.field.error'))
            ->addField(TwigField::create('correlationId', '@DddActivity/admin/grid/chain.html.twig')->setLabel('activity.field.chain'))
        ;
    }

    public function getResourceClass(): string
    {
        return ActivityEntryResource::class;
    }
}
