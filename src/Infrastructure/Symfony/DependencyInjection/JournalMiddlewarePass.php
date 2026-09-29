<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Places the journal middleware right before `handle_message`, on the command
 * and query buses — a position prepended configuration cannot guarantee.
 *
 * There, it only runs where a message is actually handled:
 * - once for a synchronous message, although the `sync` transport routes it
 *   through the bus twice (sent, then received) — only the second pass reaches
 *   the handling;
 * - in the worker for an asynchronous one, never when it is sent;
 * - inside the command's transaction, doctrine_transaction being earlier.
 *
 * Runs before Messenger's own pass, which turns these parameters into the
 * buses' middleware stacks.
 */
final class JournalMiddlewarePass implements CompilerPassInterface
{
    public const MIDDLEWARE = 'ddd_activity.journal_middleware';

    public function process(ContainerBuilder $container): void
    {
        foreach (['command.bus', 'query.bus'] as $bus) {
            $this->insertBeforeHandling($container, $bus . '.middleware');
        }
    }

    private function insertBeforeHandling(ContainerBuilder $container, string $parameter): void
    {
        if (!$container->hasParameter($parameter)) {
            return;
        }

        /** @var list<array{id: string, arguments?: array<mixed>}> $middleware */
        $middleware = $container->getParameter($parameter);
        $ids = array_column($middleware, 'id');
        if (in_array(self::MIDDLEWARE, $ids, true)) {
            return;
        }

        $position = array_search('handle_message', $ids, true);
        array_splice(
            $middleware,
            $position === false ? count($middleware) : $position,
            0,
            [['id' => self::MIDDLEWARE, 'arguments' => []]],
        );
        $container->setParameter($parameter, $middleware);
    }
}
