<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use App\Infrastructure\Doctrine\Repository\Trait\FindByIdTrait;
use App\Infrastructure\Doctrine\Repository\Trait\SaveRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

use function array_map;

class UserDoctrineRepository extends ServiceEntityRepository implements UserRepository
{
    use SaveRepositoryTrait;
    use FindByIdTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByIdentifier(string $identifier): ?User
    {
        try {
            return $this->createQueryBuilder('u')
                ->where('u.email = :email')
                ->setParameter('email', $identifier)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function findAllAwaitingTelegramLink(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.telegramUsername IS NOT NULL')
            ->andWhere('u.telegramChatId IS NULL')
            ->getQuery()
            ->getResult();
    }

    public function findOneByTelegramUsername(string $telegramUsername): ?User
    {
        return $this->findOneBy(['telegramUsername' => $telegramUsername]);
    }

    public function findOneByTelegramChatId(string $telegramChatId): ?User
    {
        return $this->findOneBy(['telegramChatId' => $telegramChatId]);
    }

    public function findAllByIds(array $ids = []): array
    {
        $qb = $this->createQueryBuilder('u');

        $qb
            ->where($qb->expr()->in('u.id', ':userIds'))
            ->setParameter('userIds', array_map(static fn (Uuid $uuid) => $uuid->toBinary(), $ids));

        return $qb
            ->getQuery()
            ->getResult();
    }
}
