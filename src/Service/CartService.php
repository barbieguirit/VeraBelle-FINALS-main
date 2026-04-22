<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * CartService manages shopping cart items stored in session
 * Handles add, remove, update, and clear operations
 */
class CartService
{
    private const CART_SESSION_KEY = 'shopping_cart';

    public function __construct(private RequestStack $requestStack) {}

    /**
     * Add or update product in cart
     * @param int $productId Product ID
     * @param string $productName Product name for display
     * @param float $price Product price
     * @param int $quantity Quantity to add
     */
    public function addToCart(int $productId, string $productName, float $price, int $quantity = 1, ?string $image = null): void
    {
        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'id'       => $productId,
                'name'     => $productName,
                'price'    => (float) $price,
                'quantity' => $quantity,
                'image'    => $image,
            ];
        }

        $this->saveCart($cart);
    }

    /**
     * Remove product from cart
     */
    public function removeFromCart(int $productId): void
    {
        $cart = $this->getCart();
        unset($cart[$productId]);
        $this->saveCart($cart);
    }

    /**
     * Update quantity of product in cart
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        $cart = $this->getCart();
        if (isset($cart[$productId])) {
            if ($quantity <= 0) {
                $this->removeFromCart($productId);
            } else {
                $cart[$productId]['quantity'] = $quantity;
                $this->saveCart($cart);
            }
        }
    }

    /**
     * Get all cart items
     */
    public function getCart(): array
    {
        return $this->requestStack->getSession()->get(self::CART_SESSION_KEY, []);
    }

    /**
     * Get cart total price
     */
    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->getCart() as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return (float) $total;
    }

    /**
     * Get cart item count (total items)
     */
    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->getCart() as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->getCart());
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(): void
    {
        $this->saveCart([]);
    }

    /**
     * Save cart to session
     */
    private function saveCart(array $cart): void
    {
        $this->requestStack->getSession()->set(self::CART_SESSION_KEY, $cart);
    }
}
