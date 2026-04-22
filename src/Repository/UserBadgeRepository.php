<?php

namespace App\Repository;

use App\Entity\UserBadge;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBadge>
 */
class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.earnedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasBadge(User $user, $badgeName)
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->andWhere('b.badgeName = :badgeName')
            ->setParameter('user', $user)
            ->setParameter('badgeName', $badgeName)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findByBadgeName($badgeName)
    {
        return $this->createQueryBuilder('b')
            ->where('b.badgeName = :badgeName')
            ->setParameter('badgeName', $badgeName)
            ->orderBy('b.earnedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
