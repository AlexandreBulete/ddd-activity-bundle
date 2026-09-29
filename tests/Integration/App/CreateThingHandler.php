<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Tests\Integration\App;

use AlexandreBulete\DddActivityBundle\Infrastructure\Journal\ActivityJournal;
use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddFoundation\Application\Command\AsCommandHandler;
use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommandHandler]
final readonly class CreateThingHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private CommandBusInterface $commands,
        private ActivityJournal $journal,
    ) {}

    public function __invoke(CreateThing $command): void
    {
        $this->em->persist(new Thing($command->name));
        $this->journal->recordEffect('thing.announced', new ActivityDescription(subjectType: 'thing', subjectId: $command->name));

        if ($command->thenCreate) {
            $this->commands->dispatch(new CreateThing($command->name . '-child'));
        }

        if ($command->thenFail) {
            throw new \DomainException('Refused by the domain.');
        }
    }
}
