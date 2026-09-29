<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Listener;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Applies `activity.table` to the journal's entity: a static XML mapping
 * cannot carry a configurable table name.
 */
final readonly class TableNameListener
{
    public function __construct(
        private string $table,
    ) {}

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        if ($metadata->getName() === ActivityEntry::class) {
            $metadata->setPrimaryTable(['name' => $this->table]);
        }
    }
}
