<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create Categories
        $womenCategory = new Category();
        $womenCategory->setName('Women\'s Clothing');
        $manager->persist($womenCategory);

        $menCategory = new Category();
        $menCategory->setName('Men\'s Clothing');
        $manager->persist($menCategory);

        $manager->flush();

        // Create Products with Images
        $products = [
            [
                'name' => 'Classic Black Dress',
                'description' => 'Elegant black dress perfect for any occasion, made from premium fabric',
                'price' => '89.99',
                'stock' => 25,
                'category' => $womenCategory,
                'image' => 'bedroom.jpg'
            ],
            [
                'name' => 'Casual White T-Shirt',
                'description' => 'Comfortable cotton t-shirt, versatile and stylish',
                'price' => '29.99',
                'stock' => 50,
                'category' => $womenCategory,
                'image' => '1722806180e36c195cbadfba189af165-69d49556a0583.jpg'
            ],
            [
                'name' => 'Blue Denim Jeans',
                'description' => 'Classic blue jeans with perfect fit and durability',
                'price' => '59.99',
                'stock' => 40,
                'category' => $womenCategory,
                'image' => '23dfe15520dca67a80d7cfcdec0c5795-69d4966d85a65.jpg'
            ],
            [
                'name' => 'Summer Floral Dress',
                'description' => 'Light and airy floral dress perfect for warm weather',
                'price' => '69.99',
                'stock' => 30,
                'category' => $womenCategory,
                'image' => '23dfe15520dca67a80d7cfcdec0c5795-69d496711b71b.jpg'
            ],
            [
                'name' => 'Leather Jacket',
                'description' => 'Premium leather jacket for a bold and stylish look',
                'price' => '149.99',
                'stock' => 15,
                'category' => $womenCategory,
                'image' => '4e1ce5b907ca07db9bd583b6419fce68-69d496074b79d.jpg'
            ],
            [
                'name' => 'Cardigan Sweater',
                'description' => 'Cozy cardigan perfect for layering in any season',
                'price' => '79.99',
                'stock' => 20,
                'category' => $womenCategory,
                'image' => '5993703042e6cf5703be40f3b7a18442-69d49c08555a6.jpg'
            ],
            [
                'name' => 'Men\'s Polo Shirt',
                'description' => 'Classic polo shirt for casual and semi-formal wear',
                'price' => '49.99',
                'stock' => 35,
                'category' => $menCategory,
                'image' => '8701092594c9fdae6c0d9ef9c33a7d84-69d495ab1a99d.jpg'
            ],
            [
                'name' => 'Men\'s Chinos',
                'description' => 'Comfortable chino pants ideal for everyday style',
                'price' => '64.99',
                'stock' => 28,
                'category' => $menCategory,
                'image' => 'b597eabb788f69f48fa3b96965922078-69d4971773ba3.jpg'
            ],
            [
                'name' => 'Athletic Leggings',
                'description' => 'High-quality leggings for workouts and casual wear',
                'price' => '54.99',
                'stock' => 45,
                'category' => $womenCategory,
                'image' => 'e8e7676dac217364f82e3d0185ddfce8-69d4948c5d2c6.jpg'
            ],
            [
                'name' => 'Wool Scarf',
                'description' => 'Warm and stylish wool scarf for cold weather',
                'price' => '39.99',
                'stock' => 60,
                'category' => $womenCategory,
                'image' => 'fd1791bd11ca555c4d67b2d155dc2ff1-69d494fb2227b.jpg'
            ],
        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setCategory($productData['category']);
            $product->setImage($productData['image']);
            
            $manager->persist($product);
        }

        $manager->flush();
    }
}
