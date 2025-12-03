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
        $this->onQueue('order-events');
    }

    /**
     * Handle the job.
     *
     * In local development this simply logs the payload. In AWS, the
     * queue connection can be configured to use SQS so this job is
     * delivered to the `order-events` queue for the Email service to
     * consume.
     */
    public function handle(): void
    {
        Log::info('OrderCreated message queued for order-events', [
            'order_number' => $this->payload['order_number'] ?? null,
        ]);
    }
}
