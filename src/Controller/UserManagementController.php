<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * UserManagementController
 * 
 * Handles all administrative operations for user management including:
 * - Listing and filtering users
 * - Creating new user accounts
 * - Editing user properties and roles
 * - Resetting user passwords
 * - Toggling user status (active/disabled)
 * - Deleting user accounts
 * 
 * All operations are restricted to ROLE_ADMIN and logged via ActivityLogger.
 * 
 * @package App\Controller
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const EMAIL_VALIDATION_ERROR = 'Please enter a valid email address.';
    private const PASSWORD_VALIDATION_ERROR = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long.';

    public function __construct(
        private ActivityLogger $activityLogger
    ) {}

    /**
     * List users with optional filtering
     * 
     * Displays all users in the system with support for:
     * - Full-text search by email
     * - Filtering by role (ROLE_ADMIN, ROLE_STAFF)
     * - Filtering by status (active, disabled)
     * 
     * Results are ordered by creation date (newest first) for better visibility
     * of recently added users.
     * 
     * @param Request $request The HTTP request containing filter parameters
     * @param UserRepository $userRepository Repository for user data access
     * @return Response Rendered user list template with filter context
     */
    #[Route('', name: 'app_admin_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        try {
            // Extract filter parameters from query string
            $search = $request->query->get('search', '');
            $role = $request->query->get('role', '');
            $status = $request->query->get('status', '');

            // Build dynamic query based on provided filters
            $queryBuilder = $userRepository->createQueryBuilder('u');

            // Email search filter (case-insensitive LIKE)
            if ($search) {
                $queryBuilder->andWhere('u.email LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            // Role filter (searches within JSON roles array)
            if ($role) {
                $queryBuilder->andWhere('u.roles LIKE :role')
                    ->setParameter('role', '%"' . $role . '"%');
            }

            // Status filter (exact match: 'active' or 'disabled')
            if ($status) {
                $queryBuilder->andWhere('u.status = :status')
                    ->setParameter('status', $status);
            }

            // Order by newest first for better UX
            $users = $queryBuilder->orderBy('u.createdAt', 'DESC')->getQuery()->getResult();

            return $this->render('admin/users/index.html.twig', [
                'users' => $users,
                'search' => $search,
                'role' => $role,
                'status' => $status,
            ]);
        } catch (ORMException $e) {
            $this->addFlash('error', 'Failed to retrieve users. Please try again later.');
            return $this->render('admin/users/index.html.twig', [
                'users' => [],
                'search' => '',
                'role' => '',
                'status' => '',
            ]);
        }
    }

    /**
     * Create a new user account
     * 
     * Handles both GET (form display) and POST (form submission).
     * 
     * Validation steps:
     * 1. Email and password are required
     * 2. Email must be unique in the system
     * 3. Password must be at least 8 characters
     * 4. Default role is ROLE_STAFF if none specified
     * 5. New users are marked as verified by default
     * 
     * The password is securely hashed using Symfony's password hasher.
     * Activity is logged for audit purposes.
     * 
     * @param Request $request Contains email, password, roles, status
     * @param EntityManagerInterface $entityManager For persisting new user
     * @param UserPasswordHasherInterface $passwordHasher For secure password hashing
     * @return Response Rendered form or redirect to users list
     */
    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $email = trim($request->request->get('email', ''));
                $password = $request->request->get('password', '');
                $roles = $request->request->all('roles') ?: ['ROLE_STAFF'];
                $status = $request->request->get('status', 'active');

                // === VALIDATION LAYER ===
                
                // 1. Required field validation
                if (empty($email)) {
                    $this->addFlash('error', 'Email address is required.');
                    return $this->redirectToRoute('app_admin_users_new');
                }

                if (empty($password)) {
                    $this->addFlash('error', 'Password is required.');
                    return $this->redirectToRoute('app_admin_users_new');
                }

                // 2. Email format validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addFlash('error', self::EMAIL_VALIDATION_ERROR);
                    return $this->redirectToRoute('app_admin_users_new');
                }

                // 3. Password strength validation
                if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                    $this->addFlash('error', self::PASSWORD_VALIDATION_ERROR);
                    return $this->redirectToRoute('app_admin_users_new');
                }

                // 4. Uniqueness check - prevent duplicate emails
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $this->addFlash('error', 'An account with this email already exists.');
                    return $this->redirectToRoute('app_admin_users_new');
                }

                // === USER CREATION ===
                
                $user = new User();
                $user->setEmail($email);
                $user->setRoles($roles);
                $user->setStatus($status);
                $user->setIsVerified(true); // Auto-verify admin-created users
                $user->setPassword($passwordHasher->hashPassword($user, $password));

                $entityManager->persist($user);
                $entityManager->flush();

                // === AUDIT LOGGING ===
                $this->activityLogger->logCreate(
                    'User',
                    $user->getId(),
                    $user->getEmail(),
                    sprintf('Email: %s, Roles: %s, Status: %s', $email, implode(', ', $roles), $status)
                );

                $this->addFlash('success', sprintf('User account created successfully for %s.', $email));
                return $this->redirectToRoute('app_admin_users_index');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to create user account. Please try again.');
                return $this->redirectToRoute('app_admin_users_new');
            }
        }

        return $this->render('admin/users/new.html.twig');
    }

    /**
     * Edit user details
     * 
     * Supports updating:
     * - Email address (must remain unique)
     * - User roles (ROLE_ADMIN, ROLE_STAFF, ROLE_USER)
     * - Account status (active, disabled)
     * 
     * Changes are immediately persisted and logged for audit trail.
     * 
     * @param Request $request Contains email, roles, status
     * @param User $user The user entity to update (resolved via ParamConverter)
     * @param EntityManagerInterface $entityManager For persisting changes
     * @return Response Rendered form or redirect to users list
     */
    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $email = trim($request->request->get('email', ''));
                $roles = $request->request->all('roles') ?: ['ROLE_STAFF'];
                $status = $request->request->get('status', 'active');

                // === VALIDATION LAYER ===
                
                if (empty($email)) {
                    $this->addFlash('error', 'Email address is required.');
                    return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
                }

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addFlash('error', self::EMAIL_VALIDATION_ERROR);
                    return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
                }

                // Check for duplicate email (if email was changed)
                if ($email !== $user->getEmail()) {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existingUser) {
                        $this->addFlash('error', 'This email is already in use by another account.');
                        return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
                    }
                }

                // === UPDATE LOGIC ===
                
                $oldValues = [
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus(),
                ];

                $user->setEmail($email);
                $user->setRoles($roles);
                $user->setStatus($status);

                $entityManager->flush();

                // === AUDIT LOGGING ===
                
                $newValues = [
                    'email' => $email,
                    'roles' => $roles,
                    'status' => $status,
                ];

                $changes = [];
                foreach ($oldValues as $key => $oldValue) {
                    if ($oldValue !== $newValues[$key]) {
                        $changes[] = sprintf('%s: %s → %s', $key, json_encode($oldValue), json_encode($newValues[$key]));
                    }
                }

                $this->activityLogger->logUpdate(
                    'User',
                    $user->getId(),
                    $user->getEmail(),
                    implode(', ', $changes) ?: 'No changes'
                );

                $this->addFlash('success', 'User account updated successfully.');
                return $this->redirectToRoute('app_admin_users_index');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to update user account. Please try again.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Reset user password
     * 
     * Allows admins to force a password reset for any user.
     * Security considerations:
     * - Password must be at least 8 characters
     * - Old password is not required (admin override)
     * - Action is logged for security audit trail
     * - Session is NOT invalidated (user remains logged in if currently active)
     * 
     * @param Request $request Contains new_password parameter
     * @param User $user Target user for password reset
     * @param EntityManagerInterface $entityManager For persisting changes
     * @param UserPasswordHasherInterface $passwordHasher For secure hashing
     * @return Response Redirect back to user edit page
     */
    #[Route('/{id}/reset-password', name: 'app_admin_users_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        try {
            $newPassword = $request->request->get('new_password', '');

            // === PASSWORD VALIDATION ===
            
            if (empty($newPassword)) {
                $this->addFlash('error', 'New password is required.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
                $this->addFlash('error', self::PASSWORD_VALIDATION_ERROR);
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            // === PASSWORD RESET LOGIC ===
            
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();

            // === AUDIT LOGGING ===
            
            $this->activityLogger->log(
                'PASSWORD_RESET',
                sprintf('Admin password reset for user: %s (ID: %d)', $user->getEmail(), $user->getId())
            );

            $this->addFlash('success', sprintf('Password reset successfully for %s.', $user->getEmail()));
            return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to reset password. Please try again.');
            return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
        }
    }

    /**
     * Toggle user account status
     * 
     * Switches user account state between:
     * - active: User can log in and use the system
     * - disabled: User cannot log in (accounts remain preserved, not deleted)
     * 
     * This is useful for temporary suspensions or deactivations without
     * losing user data and history.
     * 
     * @param User $user The user whose status will be toggled
     * @param EntityManagerInterface $entityManager For persisting changes
     * @return Response Redirect back to users list
     */
    #[Route('/{id}/toggle-status', name: 'app_admin_users_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // === STATUS TOGGLE LOGIC ===
            
            $oldStatus = $user->getStatus();
            $newStatus = $oldStatus === 'active' ? 'disabled' : 'active';
            
            $user->setStatus($newStatus);
            $entityManager->flush();

            // === AUDIT LOGGING ===
            
            $action = $newStatus === 'disabled' ? 'DISABLE' : 'ENABLE';
            $this->activityLogger->log(
                $action,
                sprintf('User %s: %s (ID: %d)', $action, $user->getEmail(), $user->getId())
            );

            $statusLabel = $newStatus === 'disabled' ? 'disabled' : 'enabled';
            $this->addFlash('success', sprintf('User %s successfully %s.', $user->getEmail(), $statusLabel));

            return $this->redirectToRoute('app_admin_users_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to update user status. Please try again.');
            return $this->redirectToRoute('app_admin_users_index');
        }
    }

    /**
     * Delete user account permanently
     * 
     * IMPORTANT: This action is irreversible!
     * 
     * Safety measures:
     * - Admins cannot delete their own account (prevents accidental lockout)
     * - Action is logged with user details for audit trail
     * - Associated data (orders, payments, etc.) retained for history
     * 
     * Consider using toggleStatus() for non-destructive account deactivation.
     * 
     * @param Request $request The HTTP request (for CSRF validation)
     * @param User $user The user to delete (resolved via ParamConverter)
     * @param EntityManagerInterface $entityManager For removing user from database
     * @return Response Redirect back to users list
     */
    #[Route('/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // === SAFETY CHECK ===
            
            // Prevent self-deletion to avoid accidental admin lockout
            if ($this->getUser() === $user) {
                $this->addFlash('error', 'You cannot delete your own admin account. Contact another administrator for account deletion.');
                return $this->redirectToRoute('app_admin_users_index');
            }

            // === DELETION LOGIC ===
            
            $username = $user->getEmail();
            $userId = $user->getId();

            $entityManager->remove($user);
            $entityManager->flush();

            // === AUDIT LOGGING ===
            
            $this->activityLogger->log(
                'DELETE',
                sprintf('PERMANENT USER DELETION: %s (ID: %d)', $username, $userId)
            );

            $this->addFlash('success', sprintf('User account %s has been permanently deleted.', $username));
            return $this->redirectToRoute('app_admin_users_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to delete user account. Please try again or contact support.');
            return $this->redirectToRoute('app_admin_users_index');
        }
    }
}
