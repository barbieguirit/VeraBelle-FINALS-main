<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\Challenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entry>
 */
class EntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entry::class);
    }

    public function findByChallengeOrderedByVotes(Challenge $challenge)
    {
        return $this->createQueryBuilder('e')
            ->where('e.challenge = :challenge')
            ->andWhere('e.status = :status')
            ->setParameter('challenge', $challenge)
            ->setParameter('status', 'published')
            ->orderBy('e.voteCount', 'DESC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByChallenge(Challenge $challenge)
    {
        return $this->createQueryBuilder('e')
            ->where('e.challenge = :challenge')
            ->andWhere('e.status = :status')
            ->setParameter('challenge', $challenge)
            ->setParameter('status', 'published')
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser($user)
    {
        return $this->createQueryBuilder('e')
            ->where('e.submittedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTopEntriesByChallenge(Challenge $challenge, $limit = 3)
    {
        return $this->createQueryBuilder('e')
            ->where('e.challenge = :challenge')
            ->andWhere('e.status = :status')
            ->setParameter('challenge', $challenge)
            ->setParameter('status', 'published')
            ->orderBy('e.voteCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByChallenge(Challenge $challenge)
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.challenge = :challenge')
            ->andWhere('e.status = :status')
            ->setParameter('challenge', $challenge)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
