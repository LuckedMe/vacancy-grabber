<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository\Trait;

use Doctrine\ORM\Exception\ORMException;

trait SaveRemoveRepositoryTrait
{
    public function save(object $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function remove(object $entity): void
    {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws ORMException
     */
    public function refresh(object $entity): void
    {
        $this->getEntityManager()->refresh($entity);
    }
}
