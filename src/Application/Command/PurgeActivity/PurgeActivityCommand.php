<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Application\Command\PurgeActivity;

use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddFoundation\Application\Activity\JournaledInterface;
use AlexandreBulete\DddFoundation\Application\Command\CommandInterface;

/**
 * Applies the retention policy. Journaled like any command: the journal keeps
 * a trace of its own purges.
 *
 * @implements CommandInterface<int<0, max>>
 */
final readonly class PurgeActivityCommand implements CommandInterface, JournaledInterface
{
    public function __construct(
        public \DateTimeImmutable $before,
    ) {}

    public function describeActivity(): ActivityDescription
    {
        return new ActivityDescription(
            summary: 'activity.summary.purged',
            summaryParams: ['before' => $this->before->format('Y-m-d')],
            details: ['before' => $this->before->format(\DATE_ATOM)],
        );
    }
}
