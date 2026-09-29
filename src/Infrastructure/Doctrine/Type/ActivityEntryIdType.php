<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine\Type;

use AlexandreBulete\DddActivityBundle\Domain\ValueObject\ActivityEntryId;
use AlexandreBulete\DddDoctrineBridge\Type\GuidType;

final class ActivityEntryIdType extends GuidType
{
    public const NAME = 'activity_entry_id';

    protected string $name = self::NAME;
    protected string $voClass = ActivityEntryId::class;
}
