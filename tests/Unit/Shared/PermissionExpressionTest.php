<?php

use App\Utilities\Access\PermissionExpression;
use Tests\Fixtures\FakeUser;
use Tests\Fixtures\Widget;

it('evaluates AND, OR, NOT and parentheses', function (string $expression, bool $expected) {
    $user = new FakeUser(['invoices.write', 'invoices.issue', 'refunds.read_own']);

    expect(PermissionExpression::parse($expression)->evaluate($user))->toBe($expected);
})->with([
    ['invoices.write&invoices.issue', true],
    ['invoices.write & payments.void', false],
    ['payments.void|invoices.issue', true],
    ['!payments.void', true],
    ['!(invoices.write|payments.void)', false],
    ['payments.void|invoices.write&invoices.issue', true],
    ['(payments.void|invoices.write)&!invoices.issue', false],
    ['(refunds.read_all|refunds.read_own)&invoices.issue', true],
]);

it('fails closed for unknown names and guests', function () {
    expect(PermissionExpression::parse('unknown.permission')->evaluate(new FakeUser))->toBeFalse()
        ->and(PermissionExpression::parse('authenticated')->evaluate(null))->toBeFalse()
        ->and(PermissionExpression::parse('authenticated')->evaluate(new FakeUser))->toBeTrue();
});

it('treats read_all as implying read_own', function () {
    expect(PermissionExpression::parse('patients.read_own')->evaluate(new FakeUser(['patients.read_all'])))->toBeTrue();
});

it('requires ownership of a specific record unless the user can read all', function () {
    $mine = new Widget(['owner_id' => 'user-1']);
    $theirs = new Widget(['owner_id' => 'user-2']);
    $doctor = new FakeUser(['widgets.read_own', 'widgets.write']);
    $admin = new FakeUser(['widgets.read_all', 'widgets.write']);
    $write = PermissionExpression::parse('widgets.write');

    expect($write->evaluate($doctor, $mine))->toBeTrue()
        ->and($write->evaluate($doctor, $theirs))->toBeFalse()
        ->and($write->evaluate($admin, $theirs))->toBeTrue()
        ->and($write->evaluate($doctor))->toBeTrue();
});

it('lists the permission names it uses', function () {
    expect(PermissionExpression::parse('(a.read|b.write)&!a.read')->permissions())->toBe(['a.read', 'b.write']);
});

it('rejects malformed expressions', function (string $expression) {
    PermissionExpression::parse($expression);
})->with(['', 'a.read &', '(a.read', 'a.read b.write', 'a.read & $'])->throws(InvalidArgumentException::class);
