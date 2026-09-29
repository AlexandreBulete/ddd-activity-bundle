<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A unique name: a second Thing with the same one fails at flush time.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_test_thing')]
class Thing
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
        public string $name,
    ) {}
}
