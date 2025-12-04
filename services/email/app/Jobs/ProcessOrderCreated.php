<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessOrderCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order payload received from the OrderCreated message.
     */
    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;

        $this->onQueue('order-events');
    }

    public function handle(): void
    {
        $recipient = $this->payload['email'] ?? null;
        $orderNumber = $this->payload['order_number'] ?? null;

        $status = 'sent';
        $error = null;

        if (! $recipient) {
            $status = 'failed';
            $error = 'Missing recipient email on OrderCreated payload.';
        } else {
            try {
                Mail::to($recipient)->send(new OrderConfirmationMail($this->payload));
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
            }
        }

        EmailLog::create([
            'type' => 'order_confirmation',
            'order_number' => $orderNumber,
            'recipient' => $recipient ?? '',
            'subject' => 'Order confirmation ' . ($orderNumber ?: ''),
            'payload' => $this->payload,
            'status' => $status,
            'error_message' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
