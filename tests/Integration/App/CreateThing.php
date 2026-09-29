<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddFoundation\Application\Authorization\Permission;
use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddFoundation\Application\Activity\JournaledInterface;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;

/**
 * @implements CommandInterface<void>
 */
#[Permission('thing.create')]
final readonly class CreateThing implements CommandInterface, JournaledInterface
{
    public function __construct(
        public string $name,
        public bool $thenFail = false,
        public bool $thenCreate = false,
    ) {}

    public function describeActivity(): ActivityDescription
    {
        return new ActivityDescription(
            subjectType: 'thing',
            subjectId: $this->name,
            summary: 'thing.created',
            summaryParams: ['name' => $this->name],
            details: ['name' => $this->name],
        );
    }
}
