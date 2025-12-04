<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order payload.
     */
    public array $order;

    public function __construct(array $order)
    {
        $this->order = $order;
    }

    public function build(): self
    {
        $orderNumber = $this->order['order_number'] ?? null;

        return $this
            ->subject('Order confirmation ' . ($orderNumber ?: ''))
            ->view('emails.order-confirmation')
            ->with([
                'order' => $this->order,
            ]);
    }
}
