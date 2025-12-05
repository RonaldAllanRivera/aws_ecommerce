<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CheckoutDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Minimal demo data for EC2: 1 paid order with a single line item
        $orders = [
            [
                'order_number' => 'DEMO-ORDER-1',
                'user_id' => null,
                'email' => 'demo@example.com',
                'customer_name' => 'Demo Customer',
                'shipping_address' => '123 Demo Street, Demo City',
                'shipping_method' => 'Standard',
                'status' => 'paid',
                'subtotal' => 19.98,
                'tax' => 0.00,
                'shipping' => 0.00,
                'total' => 19.98,
                'items' => [
                    [
                        'product_id' => 1,
                        'product_name_snapshot' => 'Demo Widget',
                        'unit_price_snapshot' => 9.99,
                        'quantity' => 2,
                    ],
                ],
            ],
        ];

        foreach ($orders as $data) {
            $order = Order::create(collect($data)->except('items')->all());

            foreach ($data['items'] as $item) {
                $lineTotal = (float) $item['unit_price_snapshot'] * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name_snapshot' => $item['product_name_snapshot'],
                    'unit_price_snapshot' => $item['unit_price_snapshot'],
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'mock',
                'provider_reference' => 'DEMO-' . $order->order_number,
                'status' => 'captured',
                'amount' => $order->total,
            ]);
        }
    }
}
