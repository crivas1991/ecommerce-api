<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Wireless Bluetooth Headphones', 'price' => 59.99, 'stock' => 40],
            ['name' => 'Mechanical Keyboard RGB', 'price' => 89.99, 'stock' => 25],
            ['name' => 'Wireless Mouse', 'price' => 24.99, 'stock' => 60],
            ['name' => '27-inch 4K Monitor', 'price' => 349.99, 'stock' => 15],
            ['name' => 'USB-C Hub 7-in-1', 'price' => 34.99, 'stock' => 50],
            ['name' => 'Portable SSD 1TB', 'price' => 99.99, 'stock' => 30],
            ['name' => 'Smartphone Stand', 'price' => 14.99, 'stock' => 100],
            ['name' => 'Noise Cancelling Earbuds', 'price' => 79.99, 'stock' => 45],
            ['name' => 'Webcam Full HD 1080p', 'price' => 44.99, 'stock' => 35],
            ['name' => 'Laptop Backpack', 'price' => 39.99, 'stock' => 55],
            ['name' => 'Smartwatch Series X', 'price' => 199.99, 'stock' => 20],
            ['name' => 'Portable Bluetooth Speaker', 'price' => 49.99, 'stock' => 40],
            ['name' => 'Ergonomic Office Chair', 'price' => 249.99, 'stock' => 10],
            ['name' => 'Standing Desk Converter', 'price' => 179.99, 'stock' => 12],
            ['name' => 'Wireless Charging Pad', 'price' => 19.99, 'stock' => 70],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['slug' => Str::slug($product['name'])],
                [
                    'name' => $product['name'],
                    'description' => 'High quality ' . strtolower($product['name']) . ', perfect for everyday use.',
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'image_url' => 'https://picsum.photos/seed/' . Str::slug($product['name']) . '/640/480',
                    'is_active' => true,
                ]
            );
        }

        // Extra random products to have more variety in the catalog.
        Product::factory()->count(10)->create();
    }
}
