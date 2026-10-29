<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f4f7; margin: 0; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
    {{-- Header --}}
    <div style="background: #4f46e5; padding: 24px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 20px;">Payment Successful</h1>
    </div>

    {{-- Body --}}
    <div style="padding: 32px;">
        <p style="font-size: 16px; color: #333;">Hello <strong>{{ $order->user->name ?? 'Customer' }}</strong>,</p>
        <p style="color: #555;">Your payment has been confirmed. Here are your order details:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #888; width: 120px;">Order ID</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #333;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #888;">Product</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #333;">{{ $order->product->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #888;">Amount</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #333; font-weight: bold;">
                    {{ \App\Models\SiteSetting::get('currency_symbol', '¥') }}{{ number_format((float)$order->amount, 2) }}
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #888;">Payment</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #333;">{{ ucfirst($order->payment_method ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #888;">Date</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #eee; color: #333;">{{ $order->updated_at->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>

        @if($order->licenses && $order->licenses->isNotEmpty())
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px; color: #166534; font-size: 14px;">Your License Key(s):</h3>
            @foreach($order->licenses as $license)
            <div style="background: #fff; border: 1px solid #d1d5db; border-radius: 4px; padding: 10px; margin-bottom: 8px; font-family: monospace; font-size: 13px; color: #4f46e5; word-break: break-all;">
                {{ $license->license_key }}
            </div>
            <p style="font-size: 12px; color: #6b7280; margin: 0;">
                Status: {{ $license->status }} | Expires: {{ $license->expire_at?->format('Y-m-d') ?? 'Never' }}
            </p>
            @endforeach
        </div>
        @endif

        <p style="color: #555; margin-top: 24px;">Thank you for your purchase!</p>
    </div>

    {{-- Footer --}}
    <div style="background: #f9fafb; padding: 16px; text-align: center; border-top: 1px solid #eee;">
        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
            &copy; {{ date('Y') }} {{ \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform')) }}. All rights reserved.
        </p>
    </div>
</div>
</body>
</html>
