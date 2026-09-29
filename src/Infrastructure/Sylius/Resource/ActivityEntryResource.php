<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Resource;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Grid\ActivityEntryGrid;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Uid\AbstractUid;

/**
 * Read-only by construction: Index is the only operation. A journal with an
 * edit button is not a journal.
 */
#[AsResource(
    alias: 'activity.activity_entry',
    section: 'admin',
    templatesDir: '@SyliusAdminUi/crud',
    routePrefix: '/admin',
    driver: false,
    operations: [
        new Index(grid: ActivityEntryGrid::class),
    ],
)]
final class ActivityEntryResource implements ResourceInterface
{
    public function __construct(
        public ?AbstractUid $id = null,
        public ?\DateTimeImmutable $occurredAt = null,
        public ?string $actor = null,
        public ?string $action = null,
        public ?string $subject = null,
        public ?string $outcome = null,
        public ?string $channel = null,
        public ?string $summary = null,
        public ?string $error = null,
        public ?string $correlationId = null,
    ) {}

    public function getId(): ?AbstractUid
    {
        return $this->id;
    }

    public static function fromModel(ActivityEntry $entry, string $summary): self
    {
        $separator = strrpos($entry->action, '\\');

        return new self(
            id: $entry->id->value(),
            occurredAt: $entry->occurredAt,
            actor: $entry->actorLabel . ($entry->actorKind === 'user' ? '' : ' (' . $entry->actorKind . ')'),
            // The FQCN is unreadable in a cell; the filter still searches it.
            action: $separator === false ? $entry->action : substr($entry->action, $separator + 1),
            subject: $entry->subjectType === null ? null : $entry->subjectType . ' #' . $entry->subjectId,
            outcome: $entry->outcome->value,
            channel: $entry->channel,
            summary: $summary,
            error: $entry->error,
            correlationId: $entry->correlationId,
        );
    }
}
