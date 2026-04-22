<?php

namespace App\Repository;

use App\Entity\Challenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Challenge>
 */
class ChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Challenge::class);
    }

    public function findActiveChallenge()
    {
        $now = new \DateTimeImmutable();
        // First try strict match (status=active AND within date range)
        $result = $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.startDate <= :now')
            ->andWhere('c.endDate >= :now')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->orderBy('c.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Fallback: just find the most recent active challenge regardless of dates
        if (!$result) {
            $result = $this->createQueryBuilder('c')
                ->where('c.status = :status')
                ->setParameter('status', 'active')
                ->orderBy('c.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $result;
    }

    public function findUpcomingChallenges($limit = 3)
    {
        return $this->createQueryBuilder('c')
            ->where('c.startDate > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPastChallenges($limit = 10)
    {
        return $this->createQueryBuilder('c')
            ->where('c.endDate < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.endDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByStatus($status)
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
