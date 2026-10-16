<?php

use App\Modules\Identity\Users\Models\User;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\ConcurrencyConflictException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Brick\Money\Money;
use Illuminate\Support\Str;
use Tests\Fixtures\Widget;

beforeEach(fn () => Widget::createTable());

it('creates generic entities with a UUID id and a gap-free yearly code', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $first = Widget::create(['name' => 'A']);
    $second = Widget::create(['name' => 'B']);
    $manual = Widget::create(['name' => 'C', 'code' => 'WID-MANUAL']);

    expect(Str::isUuid($first->id))->toBeTrue()
        ->and($first->code)->toBe('WID-2026-000001')
        ->and($second->code)->toBe('WID-2026-000002')
        ->and($manual->code)->toBe('WID-MANUAL')
        ->and($first->fresh()->is_archived)->toBeFalse()
        ->and($first->fresh()->status)->toBe(GenericStatus::Pending);
});

it('archives, soft deletes and restores without physical deletes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $widget = Widget::create(['name' => 'A']);

    $widget->archive();
    expect(Widget::notArchived()->count())->toBe(0)->and(Widget::count())->toBe(1);

    $widget->unarchive()->delete();
    expect(Widget::count())->toBe(0)
        ->and(Widget::onlyDeleted()->first()->deleted_by)->toBe($user->id)
        ->and(Widget::withDeleted()->count())->toBe(1);

    Widget::onlyDeleted()->first()->restore();
    expect(Widget::count())->toBe(1)->and(Widget::first()->deleted_at)->toBeNull();
});

it('forbids force deletes', function () {
    Widget::create(['name' => 'A'])->forceDelete();
})->throws(LogicException::class);

it('records who created and updated a row', function () {
    $creator = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($creator);
    $widget = Widget::create(['name' => 'A']);
    $this->actingAs($editor);
    $widget->update(['name' => 'B']);

    expect($widget->fresh())->created_by->toBe($creator->id)->updated_by->toBe($editor->id);
});

it('increments the version on every update and detects stale writes', function () {
    $widget = Widget::create(['name' => 'A']);
    $widget->update(['name' => 'B']);

    expect($widget->version)->toBe(2);
    $widget->assertVersion(2);
    $widget->assertVersion(1);
})->throws(ConcurrencyConflictException::class);

it('drives the generic status lifecycle', function () {
    $widget = Widget::create(['name' => 'A']);

    $widget->start()->complete();

    expect($widget->fresh()->status)->toBe(GenericStatus::Completed)
        ->and($widget->isFinal())->toBeTrue()
        ->and(Widget::completed()->count())->toBe(1)
        ->and(Widget::open()->count())->toBe(0);

    $widget->cancel('too late');
})->throws(InvalidStateTransitionException::class);

it('stores the cancellation reason', function () {
    $widget = Widget::create(['name' => 'A'])->cancel('Patient request');

    expect($widget->fresh())->status->toBe(GenericStatus::Cancelled)->cancellation_reason->toBe('Patient request');
});

it('casts money columns and serialises them as amount + currency', function () {
    $widget = Widget::create(['name' => 'A', 'price' => '12.5']);

    expect($widget->fresh()->price)->toBeInstanceOf(Money::class)
        ->and($widget->fresh()->toArray()['price'])->toBe(['amount' => '12.500', 'currency' => 'BHD']);
});

it('rejects float money values', function () {
    Widget::create(['name' => 'A', 'price' => 12.5]);
})->throws(InvalidArgumentException::class);
