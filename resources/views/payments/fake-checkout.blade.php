{{-- The fake gateway's payment page (local only): stands in for Tap's hosted page. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payments.fake_checkout.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f4; font-family: system-ui, sans-serif; color: #10302a; }
        .card { width: min(92vw, 380px); background: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 12px 40px rgb(16 48 42 / 12%); animation: rise .35s ease-out; }
        .tag { display: inline-block; font-size: 12px; padding: 2px 10px; border-radius: 99px; background: #fef3c7; color: #92400e; }
        .amount { font-size: 34px; font-weight: 700; margin: 12px 0 2px; }
        .muted { color: #5b6b67; font-size: 14px; margin: 0 0 20px; }
        a { display: block; text-align: center; padding: 13px; border-radius: 12px; text-decoration: none; font-weight: 600; margin-top: 10px; transition: transform .12s; }
        a:active { transform: scale(.97); }
        .pay { background: #0f766e; color: #fff; }
        .decline { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } }
        @media (prefers-reduced-motion: reduce) { .card { animation: none; } }
    </style>
</head>
<body>
<main class="card">
    <span class="tag">{{ __('payments.fake_checkout.test_mode') }}</span>
    <p class="amount">{{ $amount }}</p>
    <p class="muted">{{ $reference }}</p>
    <a class="pay" href="{{ URL::temporarySignedRoute('fake-checkout.decide', now()->addHour(), ['charge' => $charge, 'outcome' => 'pay']) }}">{{ __('payments.fake_checkout.pay') }}</a>
    <a class="decline" href="{{ URL::temporarySignedRoute('fake-checkout.decide', now()->addHour(), ['charge' => $charge, 'outcome' => 'decline']) }}">{{ __('payments.fake_checkout.decline') }}</a>
</main>
</body>
</html>
