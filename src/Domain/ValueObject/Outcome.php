<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Domain\ValueObject;

enum Outcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** Not allowed to ask (authorization). */
    case Refused = 'refused';
}
