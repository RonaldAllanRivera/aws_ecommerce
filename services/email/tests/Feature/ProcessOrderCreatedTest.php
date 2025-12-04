<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrderCreated;
use App\Mail\OrderConfirmationMail;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProcessOrderCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_order_created_sends_email_and_logs_success(): void
    {
        Mail::fake();

        $payload = [
            'order_number' => 'ORDER1234',
            'email' => 'customer@example.com',
            'status' => 'paid',
            'subtotal' => '100.00',
            'tax' => '0.00',
            'shipping' => '0.00',
            'total' => '100.00',
            'items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Product',
                    'unit_price' => '100.00',
                    'quantity' => 1,
                    'line_total' => '100.00',
                ],
            ],
        ];

        $job = new ProcessOrderCreated($payload);
        $job->handle();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($payload) {
            return $mail->order['order_number'] === $payload['order_number']
                && $mail->hasTo($payload['email']);
        });

        $this->assertDatabaseCount('email_logs', 1);

        $log = EmailLog::first();

        $this->assertSame('order_confirmation', $log->type);
        $this->assertSame($payload['order_number'], $log->order_number);
        $this->assertSame($payload['email'], $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertIsArray($log->payload);
    }
}
