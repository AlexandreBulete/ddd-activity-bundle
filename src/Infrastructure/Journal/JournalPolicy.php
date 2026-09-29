<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use AlexandreBulete\DddActivityBundle\Domain\ValueObject\EntryKind;
use AlexandreBulete\DddFoundation\Application\Activity\Journaled;
use AlexandreBulete\DddFoundation\Application\Activity\NotJournaled;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;
use AlexandreBulete\DddFoundation\Application\Query\QueryInterface;

/**
 * What gets journaled: every command unless marked #[NotJournaled], a query
 * only when marked #[Journaled]. Anything else on the buses (a mail message, a
 * framework message) is not a use case, and not journaled here.
 */
final class JournalPolicy
{
    /** @var array<class-string, EntryKind|null> */
    private array $decisions = [];

    public function kindOf(object $message): ?EntryKind
    {
        return $this->decisions[$message::class] ??= self::decide($message);
    }

    /**
     * The wider permission an entry is also visible with — #[Journaled(visibleWith: …)],
     * on a query or a command.
     */
    public function visibleWith(object $message): ?string
    {
        $journaled = (new \ReflectionClass($message))->getAttributes(Journaled::class);

        return $journaled === [] ? null : $journaled[0]->newInstance()->visibleWith;
    }

    private static function decide(object $message): ?EntryKind
    {
        $class = new \ReflectionClass($message);

        if ($message instanceof CommandInterface) {
            return $class->getAttributes(NotJournaled::class) === [] ? EntryKind::Command : null;
        }

        if ($message instanceof QueryInterface) {
            return $class->getAttributes(Journaled::class) === [] ? null : EntryKind::Query;
        }

        return null;
    }
}
