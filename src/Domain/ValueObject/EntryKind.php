<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Domain\ValueObject;

enum EntryKind: string
{
    /** A command handled by the application. */
    case Command = 'command';

    /** A read explicitly marked as worth journaling. */
    case Query = 'query';

    /** Something done outside: a deployment triggered, a mail sent. */
    case Effect = 'effect';
}
