<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}

    #[Route('', name: 'app_admin_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = $request->query->get('search', '');
        $role = $request->query->get('role', '');
        $status = $request->query->get('status', '');

        $queryBuilder = $userRepository->createQueryBuilder('u');

        if ($search) {
            $queryBuilder->andWhere('u.username LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $queryBuilder->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $role . '"%');
        }

        if ($status) {
            $queryBuilder->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        $users = $queryBuilder->orderBy('u.createdAt', 'DESC')->getQuery()->getResult();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
        ]);
    }

    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles') ?: ['ROLE_STAFF'];
            $status = $request->request->get('status', 'active');

            // Validation
            if (empty($username) || empty($password)) {
                $this->addFlash('error', 'Username and password are required.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            // Check if username already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                $this->addFlash('error', 'Username already exists.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setStatus($status);
            $user->setIsVerified(true);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->activityLogger->logCreate('User', $user->getId(), $user->getUsername());
            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        return $this->render('admin/users/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $roles = $request->request->all('roles') ?: ['ROLE_STAFF'];
            $status = $request->request->get('status', 'active');

            if (empty($username)) {
                $this->addFlash('error', 'Username is required.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            $user->setUsername($username);
            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setStatus($status);

            $entityManager->flush();

            $this->activityLogger->logUpdate('User', $user->getId(), $user->getUsername());
            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/reset-password', name: 'app_admin_users_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $newPassword = $request->request->get('new_password');

        if (empty($newPassword) || strlen($newPassword) < 8) {
            $this->addFlash('error', 'Password must be at least 8 characters long.');
            return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        $this->activityLogger->log('PASSWORD_RESET', sprintf('Password reset for user: %s (ID: %d)', $user->getUsername(), $user->getId()));
        $this->addFlash('success', 'Password reset successfully.');

        return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/toggle-status', name: 'app_admin_users_toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user, EntityManagerInterface $entityManager): Response
    {
        $newStatus = $user->getStatus() === 'active' ? 'disabled' : 'active';
        $user->setStatus($newStatus);
        $entityManager->flush();

        $action = $newStatus === 'disabled' ? 'DISABLE' : 'ENABLE';
        $this->activityLogger->log($action, sprintf('User: %s (ID: %d)', $user->getUsername(), $user->getId()));
        $this->addFlash('success', sprintf('User %s successfully.', $newStatus === 'disabled' ? 'disabled' : 'enabled'));

        return $this->redirectToRoute('app_admin_users_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Prevent deleting yourself
        if ($this->getUser() === $user) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        $username = $user->getUsername();
        $userId = $user->getId();

        $entityManager->remove($user);
        $entityManager->flush();

        $this->activityLogger->log('DELETE', sprintf('User: %s (ID: %d)', $username, $userId));
        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('app_admin_users_index');
    }
}
