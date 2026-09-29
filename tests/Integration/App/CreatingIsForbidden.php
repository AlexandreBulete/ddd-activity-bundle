<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionCheckerInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Actor;

/**
 * An IAM stand-in: everything is allowed but creating things.
 */
final readonly class CreatingIsForbidden implements PermissionCheckerInterface
{
    public function isGranted(Actor $actor, string $permission): bool
    {
        return $permission !== 'thing.create';
    }
}
