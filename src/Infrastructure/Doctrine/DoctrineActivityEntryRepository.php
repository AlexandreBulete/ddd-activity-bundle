<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Doctrine;

use AlexandreBulete\DddActivityBundle\Domain\Model\ActivityEntry;
use AlexandreBulete\DddActivityBundle\Domain\Repository\ActivityEntryRepositoryInterface;
use AlexandreBulete\DddDoctrineBridge\DoctrineRepository;
use AlexandreBulete\DddFoundation\Domain\ValueObject\IdentifierVO;
use Doctrine\ORM\EntityManagerInterface;

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
