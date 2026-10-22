<?php

use App\Shared\Enums\FilterFieldType;
use App\Shared\Enums\FilterOperator;
use App\Shared\Enums\LogicalOperator;
use App\Shared\Enums\SortDirection;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\FilterCondition;
use App\Shared\Query\ListQuery;
use App\Shared\Query\ListQueryConfig;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Widget;

function widgetQuery(array $params): ListQuery
{
    return ListQuery::fromArray($params, Widget::listQueryConfig());
}

function widgetNames(array $params): array
{
    return app(ApplyListQuery::class)->apply(Widget::query(), widgetQuery($params))->pluck('name')->all();
}

beforeEach(function () {
    Widget::createTable();
    $this->travelTo('2026-10-13 09:00:00');
});

it('parses pagination and both sort syntaxes', function () {
    $compact = widgetQuery(['page' => '2', 'per_page' => '25', 'sort' => '-priority,name']);
    $explicit = widgetQuery(['sort_by' => 'name', 'sort_dir' => 'desc']);
    $default = widgetQuery([]);

    expect($compact->page)->toBe(2)->and($compact->perPage)->toBe(25)
        ->and($compact->sort[0]->field)->toBe('priority')->and($compact->sort[0]->dir)->toBe(SortDirection::Desc)
        ->and($compact->sort[1]->dir)->toBe(SortDirection::Asc)
        ->and($explicit->sort[0]->dir)->toBe(SortDirection::Desc)
        ->and($default->sort[0]->field)->toBe('created_at')
        ->and($default->perPage)->toBe(15);
});

it('parses operators and nested AND / OR / NOT groups', function () {
    $query = widgetQuery(['filter' => [
        'status' => ['in' => 'pending,in_progress'],
        'or' => [['priority' => ['gte' => '3']], ['name' => ['starts' => 'Urg']]],
        'not' => ['priority' => '1'],
    ]]);

    [$status, $or, $not] = $query->filter->children;

    expect($status)->toBeInstanceOf(FilterCondition::class)
        ->and($status->op)->toBe(FilterOperator::In)
        ->and($status->value)->toBe(['pending', 'in_progress'])
        ->and($or->op)->toBe(LogicalOperator::Or)
        ->and($or->children)->toHaveCount(2)
        ->and($not->op)->toBe(LogicalOperator::Not)
        ->and($query->toMeta()['filters'])->toHaveKey('and');
});

it('rejects anything outside the whitelist with field errors', function (array $params, string $errorKey) {
    try {
        widgetQuery($params);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($errorKey);
    }
})->with([
    'unsortable field' => [['sort' => 'secret'], 'sort'],
    'unknown filter' => [['filter' => ['secret' => 'x']], 'filter.secret'],
    'operator not allowed' => [['filter' => ['status' => ['like' => 'x']]], 'filter.status'],
    'per page above max' => [['per_page' => '500'], 'per_page'],
    'bad date' => [['date_from' => '13/10/2026'], 'date_from'],
    'range too large' => [['date_from' => '2024-01-01', 'date_to' => '2026-01-01'], 'date_to'],
    'bad day in a range' => [['date_range' => '2026-10-01..22/10/2026'], 'date_to'],
    'range ends first' => [['date_range' => '2026-10-22..2026-10-01'], 'date_to'],
    'unknown date field' => [['date_field' => 'name', 'date_range' => 'today'], 'date_field'],
    'between needs two' => [['filter' => ['priority' => ['between' => '1']]], 'filter.priority'],
    'too deep' => [['filter' => ['or' => [['or' => [['or' => [['priority' => '1']]]]]]]], 'filter'],
]);

it('filters, searches and sorts on the database with a stable tie-breaker', function () {
    Widget::create(['name' => 'Alpha', 'priority' => 1]);
    Widget::create(['name' => 'Bravo 100%', 'priority' => 3])->start();
    Widget::create(['name' => 'Charlie', 'priority' => 5]);
    Widget::create(['name' => 'Delta', 'priority' => 3])->archive();

    expect(widgetNames(['sort' => 'priority,name']))->toBe(['Alpha', 'Bravo 100%', 'Charlie'])
        ->and(widgetNames(['filter' => ['priority' => ['between' => '2,5']], 'sort' => '-priority']))->toBe(['Charlie', 'Bravo 100%'])
        ->and(widgetNames(['filter' => ['or' => [['status' => 'in_progress'], ['priority' => ['gte' => 5]]]], 'sort' => 'name']))->toBe(['Bravo 100%', 'Charlie'])
        ->and(widgetNames(['filter' => ['not' => ['status' => 'pending']]]))->toBe(['Bravo 100%'])
        ->and(widgetNames(['search' => '100%']))->toBe(['Bravo 100%'])
        ->and(widgetNames(['search' => 'ALPHA']))->toBe(['Alpha'])
        ->and(widgetNames(['search' => '%']))->toBe(['Bravo 100%'])
        ->and(widgetNames(['include_archived' => 'true', 'sort' => 'name']))->toBe(['Alpha', 'Bravo 100%', 'Charlie', 'Delta']);
});

it('filters dates and timestamps by clinic-local days', function () {
    Widget::create(['name' => 'Yesterday', 'scheduled_date' => '2026-10-12']);
    Widget::create(['name' => 'Today', 'scheduled_date' => '2026-10-13']);
    // 2026-10-12 22:30 UTC is already 13 Oct in Bahrain (UTC+3).
    Widget::create(['name' => 'Late', 'completed_at' => '2026-10-12 22:30:00']);

    expect(widgetNames(['date_range' => 'today']))->toBe(['Today'])
        ->and(widgetNames(['date_field' => 'scheduled_date', 'date_from' => '2026-10-12', 'date_to' => '2026-10-12']))->toBe(['Yesterday'])
        ->and(widgetNames(['date_field' => 'completed_at', 'date_from' => '2026-10-13', 'date_to' => '2026-10-13']))->toBe(['Late'])
        ->and(widgetNames(['date_field' => 'scheduled_date', 'date_range' => '2026-10-12..2026-10-12']))->toBe(['Yesterday'])
        ->and(widgetNames(['date_field' => 'scheduled_date', 'date_range' => '2026-10-13..', 'sort' => 'name']))->toBe(['Today'])
        ->and(widgetNames(['filter' => ['completed_at' => ['null' => 'false']]]))->toBe(['Late']);
});

it('lists only soft-deleted rows when asked', function () {
    Widget::create(['name' => 'Kept']);
    Widget::create(['name' => 'Gone'])->delete();

    expect(widgetNames(['only_deleted' => 'true']))->toBe(['Gone']);
});

it('paginates with page meta or cursors', function () {
    foreach (range(1, 5) as $i) {
        Widget::create(['name' => "W{$i}", 'priority' => $i]);
    }

    $page = app(ApplyListQuery::class)->paginate(Widget::query(), widgetQuery(['per_page' => 2, 'page' => 2, 'sort' => 'priority']));
    $cursorQuery = ListQuery::fromArray(['per_page' => 2, 'sort' => 'priority'], Widget::listQueryConfig()->cursorPagination());
    $cursor = app(ApplyListQuery::class)->paginate(Widget::query(), $cursorQuery);

    expect($page->total())->toBe(5)
        ->and(collect($page->items())->pluck('name')->all())->toBe(['W3', 'W4'])
        ->and($cursor)->toBeInstanceOf(CursorPaginator::class)
        ->and($cursor->nextCursor())->not->toBeNull();
});

it('casts boolean filter values (regression found by Larastan)', function () {
    $config = ListQueryConfig::make()
        ->filterable('is_active', ['eq'], type: FilterFieldType::Boolean);

    $query = ListQuery::fromArray(['filter' => ['is_active' => 'false']], $config);

    expect($query->filter->children[0]->value)->toBeFalse();
});
