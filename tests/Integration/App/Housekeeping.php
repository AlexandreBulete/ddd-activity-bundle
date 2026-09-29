<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Authorization\Permission;
use AlexandreBulete\DddFoundation\Application\Activity\NotJournaled;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;

/**
 * @implements CommandInterface<void>
 */
#[NotJournaled]
#[Permission('thing.housekeeping')]
final readonly class Housekeeping implements CommandInterface
{
}
