<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('LOWER(o.orderStatus) = LOWER(:status)')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getOrderStatistics(): array
    {
        $total = $this->count([]);
        
        return [
            'total' => $total,
            'pending' => $this->countByStatus('pending'),
            'completed' => $this->countByStatus('completed'),
            'processing' => $this->countByStatus('processing'),
            'cancelled' => $this->countByStatus('cancelled'),
        ];
    }
}