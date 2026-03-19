<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category')]
#[IsGranted('ROLE_STAFF')]
final class CategoryController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger) {}

    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Set the creator
            $category->setCreatedBy($this->getUser());
            
            $entityManager->persist($category);
            $entityManager->flush();

            // ✅ Log the activity
            $this->activityLogger->log(
                'CREATE',
                'Category: ' . $category->getName() . ' (ID: ' . $category->getId() . ')'
            );

            $this->addFlash('success', 'Category created successfully.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        // 🔒 Check permission: Admin or Creator only
        if (!$this->isGranted('ROLE_ADMIN') && $category->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'You do not have permission to edit this category.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // ✅ Log the activity
            $this->activityLogger->log(
                'UPDATE',
                'Category: ' . $category->getName() . ' (ID: ' . $category->getId() . ')'
            );

            $this->addFlash('success', 'Category updated successfully.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        // 🔒 Check permission: Admin or Creator only
        if (!$this->isGranted('ROLE_ADMIN') && $category->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'You do not have permission to delete this category.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $categoryName = $category->getName();
                $categoryId = $category->getId();

                $entityManager->remove($category);
                $entityManager->flush();

                // ✅ Log the activity
                $this->activityLogger->log(
                    'DELETE',
                    'Category: ' . $categoryName . ' (ID: ' . $categoryId . ')'
                );

                $this->addFlash('success', 'Category deleted successfully.');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Cannot delete this category because it still has products assigned.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An unexpected error occurred while deleting the category.');
            }
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}