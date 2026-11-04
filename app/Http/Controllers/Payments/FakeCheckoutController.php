<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Services\Payments\Gateways\FakeGateway;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The fake gateway's payment page (driver `fake`, never in production; links are signed): Pay or Decline, then
 * back through the normal return route, exactly like Tap's hosted page.
 */
final class FakeCheckoutController
{
    public function show(string $charge): Response
    {
        $saved = FakeGateway::peek($charge) ?? abort(404);

        return response()->view('payments.fake-checkout', [
            'amount' => MoneyHelper::format(MoneyHelper::of($saved['charge']->amount, $saved['charge']->currency)),
            'reference' => $saved['charge']->reference,
            'charge' => $charge,
        ]);
    }

    public function decide(string $charge, string $outcome): RedirectResponse
    {
        $next = FakeGateway::decide($charge, $outcome === 'pay') ?? abort(404);

        return redirect()->away($next);
    }
}
