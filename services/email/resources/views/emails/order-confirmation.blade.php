<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order confirmation</title>
</head>
<body>
    <p>Hi,</p>

    @if(! empty($order['order_number']))
        <p>Thank you for your order <strong>{{ $order['order_number'] }}</strong>.</p>
    @else
        <p>Thank you for your order.</p>
    @endif

    <h3>Order summary</h3>

    @if(! empty($order['items']))
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th align="left">Product</th>
                    <th align="right">Unit price</th>
                    <th align="right">Qty</th>
                    <th align="right">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order['items'] as $item)
                    <tr>
                        <td>{{ $item['product_name'] ?? ('#' . ($item['product_id'] ?? '')) }}</td>
                        <td align="right">{{ $item['unit_price'] ?? '' }}</td>
                        <td align="right">{{ $item['quantity'] ?? '' }}</td>
                        <td align="right">{{ $item['line_total'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>
        Subtotal: {{ $order['subtotal'] ?? '' }}<br>
        Tax: {{ $order['tax'] ?? '' }}<br>
        Shipping: {{ $order['shipping'] ?? '' }}<br>
        <strong>Total: {{ $order['total'] ?? '' }}</strong>
    </p>

    <p>If you have any questions, just reply to this email.</p>

    <p>Regards,<br>AWS E-commerce Team</p>
</body>
</html>
