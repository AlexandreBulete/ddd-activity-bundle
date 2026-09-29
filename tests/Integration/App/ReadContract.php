<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Authorization\Permission;
use AlexandreBulete\DddFoundation\Application\Activity\Journaled;
use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;

/**
 * @implements QueryInterface<string>
 */
#[Journaled]
#[Permission('thing.read_contract')]
final readonly class ReadContract implements QueryInterface
{
}
