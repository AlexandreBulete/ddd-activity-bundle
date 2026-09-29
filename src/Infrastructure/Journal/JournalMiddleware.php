<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use AlexandreBulete\DddActivityBundle\Domain\ValueObject\EntryKind;
use AlexandreBulete\DddFoundation\Application\Activity\JournaledInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionRegistry;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\Tracer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Journals every command (and every #[Journaled] query) handled on the bus.
 *
 * Sits right before the handling, inside the command's transaction (see
 * JournalMiddlewarePass):
 * - success → written in the transaction: rolled back with it, never a lie;
 * - failure → written independently: survives the rollback.
 *
 * The EntityManager is flushed here, before the success is written, so that a
 * failure at flush time (a constraint) is journaled as a failure too.
 *
 * Its position makes it run only where a message is handled: a message sent
 * to a transport is journaled by whoever consumes it, never twice.
 *
 * Journaling a failure never hides it: if the entry cannot be written, that is
 * logged, and the original exception is what surfaces.
 *
 * Just before the authorization middleware (ddd-symfony-bundle): a refusal
 * passes through here on its way out, and is journaled as `refused`.
 */
final readonly class JournalMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JournalPolicy $policy,
        private EntryFactory $entries,
        private ActivityWriter $writer,
        private EntityManagerInterface $em,
        private PermissionRegistry $permissions,
        private JournalContext $context,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $kind = $this->policy->kindOf($message);
        if ($kind === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Before handling: a description that cannot be built is a bug, and
        // must surface before any side effect, not after.
        $description = $message instanceof JournaledInterface ? $message->describeActivity() : null;
        $trace = Tracer::traceOf($envelope);
        $permission = $this->permissions->permissionOf($message::class);
        $visibleWith = $this->policy->visibleWith($message);

        try {
            $result = $this->context->within($permission, $visibleWith, static fn (): Envelope => $stack->next()->handle($envelope, $stack));
            if ($kind === EntryKind::Command && $result->last(HandledStamp::class) !== null) {
                $this->em->flush();
            }
        } catch (\Throwable $failure) {
            try {
                $this->writer->independently($this->entries->create($trace, $kind, $message::class, $description, $permission, $visibleWith, $failure));
            } catch (\Throwable $journalFailure) {
                $this->logger->error('Could not journal the failure of {message}.', [
                    'message' => $message::class,
                    'correlation_id' => $trace->stamp->correlationId,
                    'exception' => $journalFailure,
                ]);
            }

            throw $failure;
        }

        if ($result->last(HandledStamp::class) === null) {
            return $result;
        }

        $entry = $this->entries->create($trace, $kind, $message::class, $description, $permission, $visibleWith);
        $kind === EntryKind::Command
            ? $this->writer->inTransaction($entry)
            : $this->writer->independently($entry);

        return $result;
    }
}
