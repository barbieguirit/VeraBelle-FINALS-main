<?php

namespace App\Repository;

use App\Entity\Vote;
use App\Entity\Entry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findUserVote(User $user, Entry $entry)
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->andWhere('v.entry = :entry')
            ->setParameter('user', $user)
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countVotesForEntry(Entry $entry)
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.entry = :entry')
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUserVotes(User $user)
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
