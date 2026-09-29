<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use AlexandreBulete\DddActivityBundle\Domain\ValueObject\EntryKind;
use AlexandreBulete\DddFoundation\Application\Activity\ActivityDescription;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Actor;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Trace;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceContext;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\TraceStamp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;

/**
 * For adapters that act on the outside world — trigger a deployment, send a
 * mail, write to Jira: records the effect in the chain being handled.
 *
 * Always written independently: an effect that happened stays journaled even
 * if the transaction around it is rolled back afterwards. Journaling never
 * breaks the adapter: an entry that cannot be written is logged instead.
 */
final readonly class ActivityJournal
{
    public function __construct(
        private TraceContext $context,
        private EntryFactory $entries,
        private ActivityWriter $writer,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @param string $action what was done, a stable name ("github.deployment_triggered")
     */
    public function recordEffect(string $action, ?ActivityDescription $description = null, ?\Throwable $failure = null): void
    {
        $trace = $this->context->current() ?? self::detachedTrace();

        try {
            $this->writer->independently($this->entries->create($trace, EntryKind::Effect, $action, $description, $failure));
        } catch (\Throwable $journalFailure) {
            $this->logger->error('Could not journal the effect {action}.', [
                'action' => $action,
                'correlation_id' => $trace->stamp->correlationId,
                'exception' => $journalFailure,
            ]);
        }
    }

    /**
     * An effect outside any handled message: a chain of its own, by the system.
     */
    private static function detachedTrace(): Trace
    {
        $id = (string) new Ulid();

        return new Trace(Actor::system(), new TraceStamp($id, $id, null, \PHP_SAPI === 'cli' ? 'cli' : 'http'));
    }
}
