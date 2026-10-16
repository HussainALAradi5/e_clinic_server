<?php

use App\Shared\Support\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('must run inside a transaction', function () {
    // RefreshDatabase wraps each test in a transaction; step out of it for this assertion only.
    DB::rollBack();

    try {
        expect(fn () => app(DocumentNumberGenerator::class)->next('invoice', 'INV'))->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

it('issues sequential numbers that reset every year', function () {
    $numbers = app(DocumentNumberGenerator::class);
    $next = fn (string $date): string => DB::transaction(fn () => $numbers->next('invoice', 'INV', CarbonImmutable::parse($date)));

    expect($next('2026-03-01'))->toBe('INV-2026-000001')
        ->and($next('2026-12-31'))->toBe('INV-2026-000002')
        ->and($next('2027-01-01'))->toBe('INV-2027-000001');
});

it('leaves no gap when the business transaction rolls back', function () {
    $numbers = app(DocumentNumberGenerator::class);
    $date = CarbonImmutable::parse('2026-05-05');

    try {
        DB::transaction(function () use ($numbers, $date): void {
            $numbers->next('refund', 'REF', $date);
            throw new RuntimeException('business rule failed');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => $numbers->next('refund', 'REF', $date)))->toBe('REF-2026-000001');
});
