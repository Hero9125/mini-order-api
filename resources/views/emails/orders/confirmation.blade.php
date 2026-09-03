<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — #{{ $order->id }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f4f4f4; margin:0; padding:20px; color:#333; }
        .container { max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .header { background:#1a1a2e; color:#fff; padding:30px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; letter-spacing:1px; }
        .body { padding:30px 40px; }
        .order-badge { display:inline-block; background:#e8f5e9; color:#2e7d32; border-radius:4px; padding:4px 12px; font-size:13px; font-weight:600; }
        table { width:100%; border-collapse:collapse; margin-top:20px; font-size:14px; }
        th { background:#f8f8f8; text-align:left; padding:10px 12px; font-size:12px; text-transform:uppercase; color:#666; border-bottom:2px solid #eee; }
        td { padding:10px 12px; border-bottom:1px solid #f0f0f0; }
        .total-row td { font-weight:700; border-top:2px solid #eee; border-bottom:none; font-size:15px; }
        .footer { background:#f8f8f8; padding:20px 40px; text-align:center; font-size:12px; color:#999; }
        .status-badge { display:inline-block; background:#fff3e0; color:#e65100; border-radius:20px; padding:3px 10px; font-size:12px; font-weight:600; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✓ Order Confirmed</h1>
    </div>
    <div class="body">
        <p>Hi <strong>{{ $user->name }}</strong>,</p>
        <p>Thank you for your order. Here's your receipt:</p>

        <p>
            <span class="order-badge">Order #{{ $order->id }}</span>
            &nbsp;
            <span class="status-badge">{{ ucfirst($order->status) }}</span>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="text-align:right">Qty</th>
                    <th style="text-align:right">Unit Price</th>
                    <th style="text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                <tr>
                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                    <td style="text-align:right">{{ $item->quantity }}</td>
                    <td style="text-align:right">${{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align:right">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    <td style="text-align:right">${{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($order->notes)
        <p style="margin-top:20px; font-size:13px; color:#666;"><em>Note: {{ $order->notes }}</em></p>
        @endif

        <p style="margin-top:24px; font-size:13px; color:#888;">
            Ordered on {{ $order->created_at->format('F j, Y \a\t g:i A') }}
        </p>
    </div>
    <div class="footer">
        <p>Mini Order API &mdash; This is an automated email, please do not reply.</p>
    </div>
</div>
</body>
</html>
