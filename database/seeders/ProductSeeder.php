<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Wireless Bluetooth Headphones', 'description' => 'Premium noise-cancelling over-ear headphones with 30-hour battery life.', 'price' => 79.99,  'stock' => 150, 'sku' => 'WBH-001'],
            ['name' => 'Mechanical Keyboard TKL',       'description' => 'Tenkeyless mechanical keyboard with Cherry MX Blue switches.',             'price' => 129.99, 'stock' => 80,  'sku' => 'MKB-001'],
            ['name' => 'USB-C Hub 7-in-1',              'description' => '7-port USB-C hub with HDMI, SD card reader, and 100W PD charging.',       'price' => 45.00,  'stock' => 200, 'sku' => 'HUB-001'],
            ['name' => '27-inch 4K Monitor',            'description' => 'IPS panel 4K UHD monitor with 144Hz refresh rate and HDR support.',        'price' => 499.00, 'stock' => 30,  'sku' => 'MON-001'],
            ['name' => 'Ergonomic Office Chair',        'description' => 'Fully adjustable lumbar support chair for all-day comfort.',               'price' => 349.00, 'stock' => 20,  'sku' => 'CHR-001'],
            ['name' => 'Webcam 1080p HD',               'description' => 'Full HD webcam with built-in microphone and auto light correction.',        'price' => 59.99,  'stock' => 120, 'sku' => 'CAM-001'],
            ['name' => 'Portable SSD 1TB',              'description' => 'USB 3.2 Gen2 portable SSD with up to 1050MB/s read speed.',                'price' => 89.99,  'stock' => 95,  'sku' => 'SSD-001'],
            ['name' => 'Mouse Wireless Ergonomic',      'description' => 'Vertical ergonomic wireless mouse with 6 DPI settings.',                   'price' => 39.99,  'stock' => 175, 'sku' => 'MSE-001'],
            ['name' => 'Laptop Stand Aluminum',         'description' => 'Adjustable aluminium laptop stand compatible with 10–17 inch laptops.',    'price' => 35.00,  'stock' => 250, 'sku' => 'STD-001'],
            ['name' => 'LED Desk Lamp USB',             'description' => 'Touch-sensitive dimmable LED desk lamp with USB charging port.',           'price' => 29.99,  'stock' => 300, 'sku' => 'LMP-001'],
            ['name' => 'Smart Power Strip',             'description' => '6-outlet surge protector with 4 USB ports and individual switches.',       'price' => 49.99,  'stock' => 85,  'sku' => 'PST-001'],
            ['name' => 'Cable Management Kit',          'description' => 'Comprehensive cable organization set with clips, sleeves and ties.',        'price' => 14.99,  'stock' => 500, 'sku' => 'CBL-001'],
            ['name' => 'Gaming Mouse Pad XL',           'description' => 'Extra-large non-slip desk mat with stitched edges (900×400mm).',           'price' => 22.00,  'stock' => 220, 'sku' => 'PAD-001'],
            ['name' => 'Noise Machine White',           'description' => 'White noise machine with 20 soothing sounds for sleep and focus.',          'price' => 44.99,  'stock' => 60,  'sku' => 'NZM-001'],
            ['name' => 'Mini Projector Portable',       'description' => '1080p native resolution mini projector with built-in speaker.',            'price' => 199.00, 'stock' => 15,  'sku' => 'PRJ-001'],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(['sku' => $data['sku']], $data);
        }

        // Additional random products
        Product::factory()->count(10)->create();
    }
}
