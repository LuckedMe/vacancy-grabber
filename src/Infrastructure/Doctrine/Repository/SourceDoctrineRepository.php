<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Shared\Repository\PaginatedResult;
use App\Domain\Source\Entity\Source;
use App\Domain\Source\Repository\SourceCriteria;
use App\Domain\Source\Repository\SourceRepository;
use App\Infrastructure\Doctrine\Repository\Trait\FindByIdAndUserIdTrait;
use App\Infrastructure\Doctrine\Repository\Trait\FindByIdTrait;
use App\Infrastructure\Doctrine\Repository\Trait\OrderByRepositoryTrait;
use App\Infrastructure\Doctrine\Repository\Trait\PaginatorRepositoryTrait;
use App\Infrastructure\Doctrine\Repository\Trait\QueryConditionBuilderRepositoryTrait;
use App\Infrastructure\Doctrine\Repository\Trait\SaveRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class SourceDoctrineRepository extends ServiceEntityRepository implements SourceRepository
{
    use SaveRepositoryTrait;
    use OrderByRepositoryTrait;
    use FindByIdTrait;
    use FindByIdAndUserIdTrait;
    use PaginatorRepositoryTrait;
    use QueryConditionBuilderRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    public function findAllSortByName(?Uuid $userId = null): array
    {
        return $this
            ->createBaseQuery(new SourceCriteria(userId: $userId, orderBy: 'name', orderDirection: 'asc'))
            ->getQuery()
            ->getResult();
    }

    public function findAllReadyToGrab(): array
    {
        $qb = $this->createBaseQuery(new SourceCriteria(active: true, hasUser: true, hasUrl: true));

        return $qb
            ->join('q.user', 'u')
            ->andWhere($qb->expr()->isNotNull('q.user'))
            ->getQuery()
            ->getResult();
    }

    private function createBaseQuery(SourceCriteria $criteria): QueryBuilder
    {
        [$orderBy, $orderDirection, $orderTable] = $criteria->orderBy
            ? $this->normalizeOrderBy('q', $criteria->orderBy, $criteria->orderDirection)
            : [null, null, null];

        $qb = $this
            ->createQueryBuilder('q')
            ->select('q');

        if ($criteria->userId) {
            $qb
                ->andWhere('q.user = :userId')
                ->setParameter('userId', $criteria->userId->toBinary());
        }

        // when the order by is not q (not main table)
        // join the table
        if ($orderBy && $orderTable && $orderTable !== 'q') {
            $qb
                ->leftJoin("q.{$orderTable}", $orderTable);
        }

        if ($orderBy) {
            $qb->orderBy($orderBy, $orderDirection);
        }

        if ($criteria->query !== null) {
            $qb->groupBy('q.id');
            $this->buildQueryCondition($criteria->query, $qb, [
                'q.id',
                'q.name',
                'q.url',
            ]);
        }

        if ($criteria->active !== null) {
            $qb
                ->andWhere('q.active = :active')
                ->setParameter('active', $criteria->active);
        }

        if ($criteria->hasUser !== null) {
            $qb->andWhere($qb->expr()->isNotNull('q.user'));
        }

        if ($criteria->hasUrl !== null) {
            $qb->andWhere($qb->expr()->isNotNull('q.url'));
        }

        if ($criteria->startDay !== null) {
            $qb
                ->andWhere('q.createdAt >= :startDay')
                ->setParameter('startDay', $criteria->startDay);
        }

        if ($criteria->endDay !== null) {
            $qb
                ->andWhere('q.createdAt <= :endDay')
                ->setParameter('endDay', $criteria->endDay);
        }

        return $qb;
    }

    public function getAllPaginated(SourceCriteria $criteria): PaginatedResult
    {
        return $this->paginate(
            $this->createBaseQuery($criteria),
            $criteria->page,
            $criteria->perPage,
            false,
        );
    }
}
