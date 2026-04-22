<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/product')]
class ProductController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}

    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $products = $em->getRepository(Product::class)->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function shop(Request $request, EntityManagerInterface $em): Response
    {
        $query = trim($request->query->get('q', ''));
        $products = $em->getRepository(Product::class)->findAll();

        return $this->render('product/shop.html.twig', [
            'products' => $products,
            'searchQuery' => $query,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $product = new Product();
        
        // Set the current user as the creator
        $product->setCreatedBy($this->getUser());
        
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                try {
                    // Create unique filename
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = preg_replace('~[^0-9a-z_.-]~i', '', $originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    
                    // Move the file to the uploads directory
                    $uploadsDir = $this->getParameter('uploads_directory');
                    $imageFile->move($uploadsDir, $newFilename);
                    
                    // Set the filename on the product
                    $product->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', '⚠️ Failed to upload image: ' . $e->getMessage());
                    return $this->render('product/new.html.twig', ['form' => $form->createView()]);
                }
            }
            
            $em->persist($product);
            $em->flush();

            $this->activityLogger->logCreate('Product', $product->getId(), $product->getName());
            $this->addFlash('success', '✅ Product added successfully!');
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, Product $product, EntityManagerInterface $em): Response
    {
        // Check if user can edit this product
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isCreator = $product->getCreatedBy() && $product->getCreatedBy() === $this->getUser();
        
        if (!$isAdmin && !$isCreator) {
            $this->addFlash('error', '⚠️ You do not have permission to edit this product.');
            return $this->redirectToRoute('app_product_index');
        }
        
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                try {
                    // Create unique filename
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = preg_replace('~[^0-9a-z_.-]~i', '', $originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    
                    // Move the file to the uploads directory
                    $uploadsDir = $this->getParameter('uploads_directory');
                    $imageFile->move($uploadsDir, $newFilename);
                    
                    // Set the filename on the product
                    $product->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', '⚠️ Failed to upload image: ' . $e->getMessage());
                    return $this->render('product/edit.html.twig', [
                        'form' => $form->createView(),
                        'product' => $product,
                    ]);
                }
            }
            
            $em->flush();

            $this->activityLogger->logUpdate('Product', $product->getId(), $product->getName());
            $this->addFlash('success', '✏️ Product updated successfully!');
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function delete(Request $request, Product $product, EntityManagerInterface $em): Response
    {
        // Check if user can delete this product
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isCreator = $product->getCreatedBy() && $product->getCreatedBy() === $this->getUser();
        
        if (!$isAdmin && !$isCreator) {
            $this->addFlash('error', '⚠️ You do not have permission to delete this product.');
            return $this->redirectToRoute('app_product_index');
        }
        
        $submittedToken = $request->request->get('_token');

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $submittedToken)) {
            $productName = $product->getName();
            $productId = $product->getId();
            
            $em->remove($product);
            $em->flush();
            
            $this->activityLogger->logDelete('Product', $productId, $productName);
            $this->addFlash('success', '🗑️ Product deleted successfully!');
        } else {
            $this->addFlash('error', '⚠️ Invalid CSRF token. Please try again.');
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        // Staff/admin see the dashboard detail view
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->render('product/show.html.twig', ['product' => $product]);
        }

        // Everyone else sees the public landing page view
        return $this->render('product/show_public.html.twig', ['product' => $product]);
    }
}