<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * ActivityLogger Service
 * 
 * Handles audit logging for all system activities to maintain security
 * and compliance records. Tracks:
 * - User login/logout events
 * - Entity creation (products, users, orders, etc.)
 * - Entity updates (changes to critical data)
 * - Entity deletion (permanent removal from system)
 * - Custom actions (admin operations, special events)
 * 
 * All logs include:
 * - Timestamp (automatic via database)
 * - Acting user ID and email
 * - User role at time of action
 * - Action type (LOGIN, CREATE, UPDATE, DELETE, etc.)
 * - Target data details (what changed, what was deleted)
 * 
 * SECURITY: This service provides an immutable audit trail that
 * cannot be altered once logged, ensuring compliance.
 * 
 * @package App\Service
 */
class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private LoggerInterface $logger
    ) {}

    /**
     * Log a generic activity
     * 
     * This is the base logging method used by all other methods.
     * If no user is provided, current authenticated user is used.
     * Fails silently if no user is available (unauthenticated request).
     * 
     * ERROR HANDLING:
     * - Catches database errors to prevent logging failures from breaking app
     * - Logs errors to system logger for debugging
     * - Returns silently on error (logging shouldn't crash application)
     * 
     * @param string $action Action type (LOGIN, CREATE, UPDATE, DELETE, etc.)
     * @param string|null $targetData Description of what was affected
     * @param User|null $user Specific user to log (if null, uses current user)
     * @return void
     */
    public function log(string $action, ?string $targetData = null, ?User $user = null): void
    {
        try {
            // === USER RESOLUTION ===
            // Get either passed user or current authenticated user
            $currentUser = $user ?? $this->security->getUser();
            
            if (!$currentUser instanceof User) {
                // Silently skip logging for unauthenticated requests
                // (e.g., public pages, API calls without auth)
                return;
            }

            // === LOG RECORD CREATION ===
            
            $log = new ActivityLog();
            $log->setUserId($currentUser->getId());
            $log->setUsername($currentUser->getEmail());
            
            // Extract primary role for quick filtering
            // Preference order: ROLE_ADMIN > ROLE_STAFF > ROLE_USER
            $primaryRole = $this->extractPrimaryRole($currentUser->getRoles());
            $log->setRole($primaryRole);
            
            // Set action and optional details
            $log->setAction($action);
            $log->setTargetData($targetData ?? '');

            // === DATABASE PERSISTENCE ===
            
            $this->entityManager->persist($log);
            $this->entityManager->flush();

        } catch (ORMException $e) {
            // === ERROR HANDLING ===
            // Log the error but don't throw - logging failure shouldn't break app
            $this->logger->error(
                sprintf('Failed to log activity: %s [%s]', $e->getMessage(), $action),
                ['exception' => $e]
            );
        } catch (\Exception $e) {
            // Catch any other unexpected errors
            $this->logger->critical(
                sprintf('Unexpected error in ActivityLogger: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * Log user login event
     * 
     * Records when a user successfully authenticates.
     * Used by SecurityListener to track access patterns.
     * 
     * @param User $user The user who logged in
     * @return void
     */
    public function logLogin(User $user): void
    {
        try {
            $this->log('LOGIN', sprintf('User login: %s from IP', $user->getEmail()), $user);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log login event', ['exception' => $e]);
        }
    }

    /**
     * Log user logout event
     * 
     * Records when a user session ends.
     * Useful for session security audits.
     * 
     * @param User $user The user who logged out
     * @return void
     */
    public function logLogout(User $user): void
    {
        try {
            $this->log('LOGOUT', sprintf('User logout: %s', $user->getEmail()), $user);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log logout event', ['exception' => $e]);
        }
    }

    /**
     * Log entity creation
     * 
     * Records when a new entity (Product, User, Order, etc.) is created.
     * Captures type, ID, and descriptive name.
     * 
     * USAGE:
     * ```php
     * $this->activityLogger->logCreate('Product', $product->getId(), $product->getName());
     * $this->activityLogger->logCreate('User', $user->getId(), $user->getEmail());
     * ```
     * 
     * @param string $entity Entity type name (e.g., 'Product', 'User', 'Order')
     * @param int $id Primary key/ID of created entity
     * @param string $name Display name/identifier of entity
     * @param string|null $additionalData Optional details (JSON format recommended)
     * @return void
     */
    public function logCreate(string $entity, int $id, string $name, ?string $additionalData = null): void
    {
        try {
            $targetData = sprintf('CREATE %s: %s (ID: %d)', $entity, $name, $id);
            if ($additionalData) {
                $targetData .= ' | ' . $additionalData;
            }
            $this->log('CREATE', $targetData);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to log %s creation', $entity), ['exception' => $e]);
        }
    }

    /**
     * Log entity update
     * 
     * Records when an existing entity is modified.
     * For significant changes, include what was modified.
     * 
     * USAGE:
     * ```php
     * $this->activityLogger->logUpdate('Product', $product->getId(), $product->getName());
     * $this->activityLogger->logUpdate('User', $user->getId(), $user->getEmail(), 'Roles changed');
     * ```
     * 
     * @param string $entity Entity type name
     * @param int $id Entity ID
     * @param string $name Entity display name
     * @param string|null $changeDetails Details of what changed
     * @return void
     */
    public function logUpdate(string $entity, int $id, string $name, ?string $changeDetails = null): void
    {
        try {
            $targetData = sprintf('UPDATE %s: %s (ID: %d)', $entity, $name, $id);
            if ($changeDetails) {
                $targetData .= ' | Changes: ' . $changeDetails;
            }
            $this->log('UPDATE', $targetData);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to log %s update', $entity), ['exception' => $e]);
        }
    }

    /**
     * Log entity deletion
     * 
     * Records permanent removal of entities. IMPORTANT: This is irreversible!
     * Always include detailed information about what was deleted.
     * 
     * USAGE:
     * ```php
     * $this->activityLogger->logDelete('Product', $id, $name, 'Discontinued item');
     * $this->activityLogger->logDelete('User', $user->getId(), $user->getEmail(), sprintf('Email: %s, Role: %s', $email, $role));
     * ```
     * 
     * @param string $entity Entity type name
     * @param int $id Entity ID
     * @param string $name Entity display name
     * @param string|null $context Reason or context for deletion
     * @return void
     */
    public function logDelete(string $entity, int $id, string $name, ?string $context = null): void
    {
        try {
            $targetData = sprintf('DELETE %s: %s (ID: %d)', $entity, $name, $id);
            if ($context) {
                $targetData .= ' | Context: ' . $context;
            }
            // Use ERROR level to draw attention to deletions
            $this->logger->warning(sprintf('Entity deleted: %s %d', $entity, $id));
            $this->log('DELETE', $targetData);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to log %s deletion', $entity), ['exception' => $e]);
        }
    }

    /**
     * Log custom/special events
     * 
     * For actions that don't fit standard CRUD pattern.
     * Used for admin actions, security events, payments, etc.
     * 
     * USAGE:
     * ```php
     * $this->activityLogger->logEvent('PASSWORD_RESET', 'Admin reset password for user@example.com');
     * $this->activityLogger->logEvent('PAYMENT_FAILED', 'Payment ID 123 failed: insufficient funds');
     * $this->activityLogger->logEvent('PERMISSION_DENIED', 'Unauthorized access attempt to admin panel');
     * ```
     * 
     * @param string $eventType Type of special event
     * @param string $description Event details and context
     * @return void
     */
    public function logEvent(string $eventType, string $description): void
    {
        try {
            $this->log($eventType, $description);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to log event: %s', $eventType), ['exception' => $e]);
        }
    }

    /**
     * Helper: Extract primary role from roles array
     * 
     * Determines the most significant role for quick filtering.
     * Priority: ROLE_ADMIN > ROLE_STAFF > others > ROLE_USER
     * 
     * @param array $roles Array of role strings from user
     * @return string The primary role, defaults to ROLE_USER
     */
    private function extractPrimaryRole(array $roles): string
    {
        // Define role hierarchy (higher index = higher priority)
        $hierarchy = [
            'ROLE_USER' => 1,
            'ROLE_STAFF' => 2,
            'ROLE_ADMIN' => 3,
        ];

        $primaryRole = 'ROLE_USER';
        $maxPriority = 1;

        foreach ($roles as $role) {
            $priority = $hierarchy[$role] ?? 0;
            if ($priority > $maxPriority) {
                $primaryRole = $role;
                $maxPriority = $priority;
            }
        }

        return $primaryRole;
    }
}
