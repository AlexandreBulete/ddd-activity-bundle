<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Authorization\Permission;
use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;

/**
 * @implements QueryInterface<string>
 */
#[Permission('thing.list')]
final readonly class ListThings implements QueryInterface
{
}
