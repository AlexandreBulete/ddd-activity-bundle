<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddDoctrineBridge\DoctrineRepository;
use AlexandreBulete\DddFoundation\Domain\ValueObject\IdentifierVO;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends DoctrineRepository<ActivityEntry>
 */
final class DoctrineActivityEntryRepository extends DoctrineRepository implements ActivityEntryRepositoryInterface
{
    private const ALIAS = 'entry';

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ActivityEntry::class, self::ALIAS);
    }

    public function findById(IdentifierVO $id): ?ActivityEntry
    {
        return $this->em->find(ActivityEntry::class, $id->value());
    }

    public function visibleTo(array $permissions, ?string $actorId): static
    {
        return $this->constrained(static function (QueryBuilder $qb, string $alias) use ($permissions, $actorId): void {
            $visible = $qb->expr()->orX();
            if ($permissions !== []) {
                $visible->add("{$alias}.permission IN (:visible_permissions)");
                $visible->add("{$alias}.visibleWith IN (:visible_permissions)");
                $qb->setParameter('visible_permissions', $permissions);
            }
            if ($actorId !== null) {
                $visible->add("{$alias}.actorId = :visible_actor");
                $qb->setParameter('visible_actor', $actorId);
            }

            // Holding nothing and being nobody: nothing to see.
            $qb->andWhere($visible->count() > 0 ? $visible : '1 = 0');
        });
    }

    public function findChain(string $correlationId): array
    {
        /** @var list<ActivityEntry> */
        return $this->query()
            ->andWhere(self::ALIAS . '.correlationId = :correlation')
            ->setParameter('correlation', $correlationId)
            ->orderBy(self::ALIAS . '.occurredAt', 'ASC')
            ->addOrderBy(self::ALIAS . '.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function purgeBefore(\DateTimeImmutable $before): int
    {
        $removed = $this->em->createQueryBuilder()
            ->delete(ActivityEntry::class, self::ALIAS)
            ->where(self::ALIAS . '.occurredAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();

        return is_int($removed) && $removed > 0 ? $removed : 0;
    }
}
