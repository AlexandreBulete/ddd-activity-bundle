<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Domain\ValueObject\ActivityEntryId;
use AlexandreBulete\DddActivityBundle\Domain\ValueObject\EntryKind;
use AlexandreBulete\DddActivityBundle\Domain\ValueObject\Outcome;
use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionDenied;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Trace;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Builds an entry from a trace (who, which chain) and a description (what).
 */
final readonly class EntryFactory
{
    private const ERROR_MAX_LENGTH = 1000;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function create(
        Trace $trace,
        EntryKind $kind,
        string $action,
        ?ActivityDescription $description,
        ?string $permission = null,
        ?string $visibleWith = null,
        ?\Throwable $failure = null,
    ): ActivityEntry {
        return ActivityEntry::record(
            id: ActivityEntryId::generate(),
            occurredAt: $this->clock->now(),
            kind: $kind,
            action: $action,
            outcome: match (true) {
                $failure === null => Outcome::Succeeded,
                $failure instanceof PermissionDenied => Outcome::Refused,
                default => Outcome::Failed,
            },
            permission: $permission,
            visibleWith: $visibleWith,
            actorKind: $trace->actor->kind->value,
            actorId: $trace->actor->id,
            actorLabel: $trace->actor->label,
            actorCredential: $trace->actor->credential,
            channel: $trace->stamp->channel,
            correlationId: $trace->stamp->correlationId,
            causationId: $trace->stamp->causationId,
            messageId: $kind === EntryKind::Effect ? null : $trace->stamp->messageId,
            error: $failure === null ? null : self::summarize($failure),
            subjectType: $description?->subjectType,
            subjectId: $description?->subjectId,
            summary: $description?->summary,
            summaryParams: $description === null ? [] : $description->summaryParams,
            details: $description === null ? [] : $description->details,
        );
    }

    /**
     * The handler's exception, not Messenger's wrapper around it; its short
     * class and message, bounded.
     */
    private static function summarize(\Throwable $failure): string
    {
        if ($failure instanceof HandlerFailedException) {
            $wrapped = current($failure->getWrappedExceptions());
            $failure = $wrapped === false ? $failure : $wrapped;
        }

        $summary = (new \ReflectionClass($failure))->getShortName() . ': ' . $failure->getMessage();

        return mb_strlen($summary) > self::ERROR_MAX_LENGTH
            ? mb_substr($summary, 0, self::ERROR_MAX_LENGTH - 1) . '…'
            : $summary;
    }
}
