<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/stock')]
class StockController extends AbstractController
{
    #[Route('/', name: 'app_stock_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $products = $em->getRepository(Product::class)->findBy([], ['updatedAt' => 'DESC']);
        $stocks = $em->getRepository(Stock::class)->findBy([], ['date' => 'DESC']);

        // Keep only the latest stock movement for each product.
        $latestStocks = [];
        foreach ($stocks as $stock) {
            $product = $stock->getProduct();
            if (!$product) {
                continue;
            }

            $productId = $product->getId();
            if ($productId !== null && !isset($latestStocks[$productId])) {
                $latestStocks[$productId] = $stock;
            }
        }

        return $this->render('stock/index.html.twig', [
            'products' => $products,
            'stocks' => $stocks,
            'latestStocks' => $latestStocks,
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $stock = new Stock();

        $productId = $request->query->getInt('product');
        if ($productId > 0) {
            $product = $em->getRepository(Product::class)->find($productId);
            if ($product) {
                $stock->setProduct($product);
            }
        }
        
        // ✅ Set the current user as the creator
        $stock->setCreatedBy($this->getUser());
        
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Update product stock automatically
            $product = $stock->getProduct();
            if ($stock->getType() === 'Added' || $stock->getType() === 'Returned') {
                $product->setStock($product->getStock() + $stock->getQuantity());
            } elseif ($stock->getType() === 'Sold') {
                $product->setStock($product->getStock() - $stock->getQuantity());
            }

            $em->persist($stock);
            $em->flush();

            $this->addFlash('success', '✅ Stock record added successfully!');
            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $em): Response
    {
        // ✅ Check if user has permission to edit
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());
        $isCreator = $stock->getCreatedBy() && $stock->getCreatedBy()->getId() === $user->getId();
        
        if (!$isAdmin && !$isCreator) {
            $this->addFlash('error', '❌ You do not have permission to edit this stock entry.');
            return $this->redirectToRoute('app_stock_index');
        }

        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '✏️ Stock record updated!');
            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/edit.html.twig', [
            'form' => $form->createView(),
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $em): Response
    {
        // ✅ Check if user has permission to delete
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());
        $isCreator = $stock->getCreatedBy() && $stock->getCreatedBy()->getId() === $user->getId();
        
        if (!$isAdmin && !$isCreator) {
            $this->addFlash('error', '❌ You do not have permission to delete this stock entry.');
            return $this->redirectToRoute('app_stock_index');
        }

        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->request->get('_token'))) {
            $em->remove($stock);
            $em->flush();
            $this->addFlash('success', '🗑️ Stock record deleted.');
        }

        return $this->redirectToRoute('app_stock_index');
    }
}