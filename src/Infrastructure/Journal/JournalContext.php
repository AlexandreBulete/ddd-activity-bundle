<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Journal;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The visibility of the message being handled, for the effects it causes: an
 * effect is visible to whoever may see the command that triggered it — seeing
 * "deploy lapsa" but not "deployment triggered" would make no sense.
 */
final class JournalContext implements ResetInterface
{
    /** @var list<array{permission: string|null, visibleWith: string|null}> */
    private array $stack = [];

    /**
     * @return array{permission: string|null, visibleWith: string|null}|null
     */
    public function current(): ?array
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function within(?string $permission, ?string $visibleWith, callable $operation): mixed
    {
        $this->stack[] = ['permission' => $permission, 'visibleWith' => $visibleWith];

        try {
            return $operation();
        } finally {
            array_pop($this->stack);
        }
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}
