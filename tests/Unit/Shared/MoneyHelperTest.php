<?php

use App\Shared\Support\MoneyHelper;

it('builds BHD money with 3 decimals and serialises it for the API', function () {
    $money = MoneyHelper::of('25.5');

    expect(MoneyHelper::toApi($money))->toBe(['amount' => '25.500', 'currency' => 'BHD'])
        ->and(MoneyHelper::format($money))->toBe('BHD 25.500')
        ->and(format_money(money('1')))->toBe('BHD 1.000');
});

it('sums and takes percentages with HALF_UP rounding', function () {
    expect(MoneyHelper::toDb(MoneyHelper::sum(money('1.250'), money('2.125'))))->toBe('3.375')
        ->and(MoneyHelper::toDb(MoneyHelper::sum()))->toBe('0.000')
        ->and(MoneyHelper::toDb(MoneyHelper::percentage(money('10.005'), '10')))->toBe('1.001');
});

it('rejects floats', function () {
    MoneyHelper::of(1.5);
})->throws(TypeError::class);

it('masks values for logs', function () {
    expect(mask('+97333123456'))->toBe('********3456')->and(mask('12'))->toBe('**');
});
