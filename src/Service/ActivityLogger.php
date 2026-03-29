<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function log(string $action, ?string $targetData = null, ?User $user = null): void
    {
        $currentUser = $user ?? $this->security->getUser();
        
        if (!$currentUser instanceof User) {
            // If no user is authenticated, we can't log
            return;
        }

        $log = new ActivityLog();
        $log->setUserId($currentUser->getId());
        $log->setUsername($currentUser->getEmail());
        
        // Get the primary role (first non-ROLE_USER role)
        $roles = $currentUser->getRoles();
        $primaryRole = 'ROLE_USER';
        foreach ($roles as $role) {
            if ($role !== 'ROLE_USER') {
                $primaryRole = $role;
                break;
            }
        }
        $log->setRole($primaryRole);
        $log->setAction($action);
        $log->setTargetData($targetData);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function logLogin(User $user): void
    {
        $this->log('LOGIN', 'User logged in', $user);
    }

    public function logLogout(User $user): void
    {
        $this->log('LOGOUT', 'User logged out', $user);
    }

    public function logCreate(string $entity, int $id, string $name): void
    {
        $this->log('CREATE', sprintf('%s: %s (ID: %d)', $entity, $name, $id));
    }

    public function logUpdate(string $entity, int $id, string $name): void
    {
        $this->log('UPDATE', sprintf('%s: %s (ID: %d)', $entity, $name, $id));
    }

    public function logDelete(string $entity, int $id, string $name): void
    {
        $this->log('DELETE', sprintf('%s: %s (ID: %d)', $entity, $name, $id));
    }
}
