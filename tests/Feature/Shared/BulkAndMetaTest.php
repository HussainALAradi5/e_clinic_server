<?php

use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Enums\ExportFormat;
use App\Shared\Exceptions\ConflictException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Widget;

final class WidgetInUseException extends ConflictException
{
    protected string $errorCode = 'WIDGET_IN_USE';
}

beforeEach(function () {
    Widget::createTable();

    app(BulkActionRegistry::class)
        ->register('widgets', Widget::class, Widget::listQueryConfig())
        ->action('start', 'widgets.write&widgets.start', fn (Widget $w) => $w->start())
        ->action('cancel', 'widgets.write', fn (Widget $w, ?string $reason) => $w->priority > 4
            ? throw new WidgetInUseException('Widget is in use.')
            : $w->cancel($reason), needsReason: true)
        ->exportable(JsonResource::class, formats: [ExportFormat::Csv, ExportFormat::Xlsx]);

    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(fn () => Route::bulkActions('widgets', 'widgets'));
});

it('archives selected ids and reports per-record failures', function () {
    actingWith('widgets.read_all', 'widgets.write');
    $a = Widget::create(['name' => 'A']);
    $b = Widget::create(['name' => 'B']);

    $this->postJson('/api/v1/widgets/bulk', ['action' => 'archive', 'ids' => [$a->id, $b->id, 'missing-id']])
        ->assertOk()
        ->assertJsonPath('data.requested', 3)
        ->assertJsonPath('data.succeeded', 2)
        ->assertJsonPath('data.failed.0', ['id' => 'missing-id', 'code' => 'NOT_FOUND', 'message' => 'The requested record was not found.'])
        ->assertJsonPath('data.succeeded_ids', [$a->id, $b->id])
        ->assertJsonPath('data.inverse', 'unarchive');

    expect(Widget::notArchived()->count())->toBe(0);
});

it('runs business actions on everything matching a filter, keeping failures isolated', function () {
    actingWith('widgets.read_all', 'widgets.write');
    Widget::create(['name' => 'Low', 'priority' => 1]);
    Widget::create(['name' => 'High', 'priority' => 5]);
    Widget::create(['name' => 'Other', 'priority' => 2])->start();
    Widget::create(['name' => 'Excluded', 'priority' => 1]);

    $this->postJson('/api/v1/widgets/bulk', [
        'action' => 'cancel',
        'reason' => 'Clinic closed',
        'filter' => ['filter' => ['status' => 'pending', 'id' => ['nin' => [Widget::where('name', 'Excluded')->value('id')]]]],
    ])->assertOk()
        ->assertJsonPath('data.requested', 2)
        ->assertJsonPath('data.succeeded', 1)
        ->assertJsonPath('data.failed.0.code', 'WIDGET_IN_USE');

    expect(Widget::cancelled()->pluck('name')->all())->toBe(['Low']);
});

it('previews with dry_run without changing anything', function () {
    actingWith('widgets.read_all', 'widgets.delete');
    $widget = Widget::create(['name' => 'A']);

    $this->postJson('/api/v1/widgets/bulk', ['action' => 'delete', 'ids' => [$widget->id], 'dry_run' => true])
        ->assertOk()->assertJsonPath('data.succeeded', 1)->assertJsonPath('data.dry_run', true);

    expect(Widget::count())->toBe(1);
});

it('refuses actions the user may not run and validates the request', function () {
    actingWith('widgets.read_all', 'widgets.write');
    $widget = Widget::create(['name' => 'A']);

    $this->postJson('/api/v1/widgets/bulk', ['action' => 'start', 'ids' => [$widget->id]])
        ->assertForbidden()->assertJsonPath('error_code', 'BULK_ACTION_NOT_ALLOWED');
    $this->postJson('/api/v1/widgets/bulk', ['action' => 'cancel', 'ids' => [$widget->id]])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['reason']]);
    $this->postJson('/api/v1/widgets/bulk', ['action' => 'archive', 'ids' => [$widget->id], 'filter' => ['search' => 'A']])
        ->assertStatus(422);
});

it('only lets owners act on their own records', function () {
    $user = actingWith('widgets.read_own', 'widgets.write');
    $mine = Widget::create(['name' => 'Mine', 'owner_id' => $user->id]);
    $theirs = Widget::create(['name' => 'Theirs']);

    $this->postJson('/api/v1/widgets/bulk', ['action' => 'archive', 'ids' => [$mine->id, $theirs->id]])
        ->assertOk()->assertJsonPath('data.succeeded', 1)->assertJsonPath('data.failed.0.code', 'FORBIDDEN');
});

it('publishes the resource schema filtered by permissions', function () {
    actingWith('widgets.read_all', 'widgets.write', 'widgets.export');

    $this->getJson('/api/v1/meta/resources/widgets')
        ->assertOk()
        ->assertJsonPath('data.resource', 'widgets')
        ->assertJsonPath('data.filters.0', ['field' => 'status', 'label' => 'Status', 'type' => 'enum', 'operators' => ['eq', 'ne', 'in', 'nin'], 'enum' => 'GenericStatus'])
        // Labels: given, else the column's ("Name"), else the field in words; internal filters are hidden from the panel.
        ->assertJsonPath('data.filters.*.label', ['Status', 'Priority', 'Name', 'Completed at', 'Responsible'])
        ->assertJsonPath('data.filters.3.hidden', true)
        // Name is in the search box already: no second text box for it in the panel.
        ->assertJsonPath('data.filters.2.hidden', true)
        ->assertJsonMissingPath('data.filters.0.hidden')
        ->assertJsonPath('data.search', true)
        ->assertJsonPath('data.columns.0', ['field' => 'code', 'label' => 'Code'])
        ->assertJsonPath('data.bulk_actions.*.name', ['archive', 'unarchive', 'cancel'])
        ->assertJsonPath('data.export_formats', ['csv', 'xlsx']);

    $this->getJson('/api/v1/meta/resources/unknown')->assertNotFound()->assertJsonPath('error_code', 'UNKNOWN_RESOURCE');
});

it('hides resources the user cannot read', function () {
    actingWith('other.read_all');

    $this->getJson('/api/v1/meta/resources/widgets')->assertNotFound();
});

it('publishes enums with labels and transitions', function () {
    actingWith();

    $this->getJson('/api/v1/meta/enums')
        ->assertOk()
        ->assertJsonPath('data.GenericStatus.options.0', ['value' => 'pending', 'label' => 'Pending'])
        ->assertJsonPath('data.GenericStatus.transitions.pending', ['in_progress', 'cancelled'])
        ->assertJsonPath('data.DayOfWeek.options.0', ['value' => 0, 'label' => 'Sunday'])
        // Plain words in sentence case; abbreviations and hyphens written out where it matters.
        ->assertJsonPath('data.TreatmentType.options.6', ['value' => 'respiratory_therapy', 'label' => 'Respiratory therapy'])
        ->assertJsonPath('data.AppointmentType.options.1', ['value' => 'follow_up', 'label' => 'Follow-up'])
        ->assertJsonPath('data.ClinicalOrderType.options.0', ['value' => 'xray', 'label' => 'X-ray']);
});
