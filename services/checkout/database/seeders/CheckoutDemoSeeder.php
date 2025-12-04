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
        // Simple deterministic demo data: 2 orders, each with items and a payment.
        $orders = [
            [
                'order_number' => Str::upper(Str::random(12)),
                'user_id' => null,
                'email' => 'demo1@example.com',
                'customer_name' => 'Demo Customer 1',
                'shipping_address' => '123 Demo Street, Demo City',
                'shipping_method' => 'Standard',
                'status' => 'paid',
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 5.00,
                'total' => 115.00,
                'items' => [
                    [
                        'product_id' => 1,
                        'product_name_snapshot' => 'Demo Product A',
                        'unit_price_snapshot' => 50.00,
                        'quantity' => 2,
                    ],
                ],
            ],
            [
                'order_number' => Str::upper(Str::random(12)),
                'user_id' => null,
                'email' => 'demo2@example.com',
                'customer_name' => 'Demo Customer 2',
                'shipping_address' => '456 Example Road, Sample Town',
                'shipping_method' => 'Express',
                'status' => 'paid',
                'subtotal' => 60.00,
                'tax' => 6.00,
                'shipping' => 0.00,
                'total' => 66.00,
                'items' => [
                    [
                        'product_id' => 2,
                        'product_name_snapshot' => 'Demo Product B',
                        'unit_price_snapshot' => 30.00,
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
