<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderCreatedMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order payload to send to the order-events queue.
     */
    public function __construct(public array $payload)
    {
        // Keep the same queue name used by the Checkout service
        $this->onQueue('order-events');
    }

    /**
     * Handle the job.
     *
     * This job is deserialized from the SQS message written by the Checkout
     * service. Here in the Email service we translate it into a
     * ProcessOrderCreated job that actually sends the SES email and logs it.
     */
    public function handle(): void
    {
        try {
            ProcessOrderCreated::dispatchSync($this->payload);
        } catch (\Throwable $e) {
            Log::error('Failed to process OrderCreated message in Email service', [
                'order_number' => $this->payload['order_number'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
