<?php

use App\Modules\Identity\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $controller = new class
    {
        use ApiResponse;

        public function show(): JsonResponse
        {
            return $this->created(['id' => 'x'], 'Created.');
        }
    };

    Route::middleware('api')->prefix('api/v1/_test')->group(function () use ($controller): void {
        Route::get('ok', fn () => $controller->show());
        Route::get('conflict', fn () => throw new InvalidStateTransitionException(context: ['from' => 'paid']));
        Route::post('validate', fn (Request $r) => $r->validate(['name' => 'required']));
        Route::get('auth', fn () => 'ok')->middleware('auth:sanctum');
        Route::get('access', fn () => 'ok')->middleware(['auth:sanctum', 'access:invoices.write&invoices.issue']);
        Route::post('pay', fn () => response()->json(['n' => Str::random(8)], 201))->middleware(['auth:sanctum', 'idempotent']);
    });
});

it('wraps success responses and echoes the request id with security headers', function () {
    $this->getJson('/api/v1/_test/ok', ['X-Request-Id' => 'req-12345678'])
        ->assertCreated()
        ->assertExactJson(['success' => true, 'message' => 'Created.', 'data' => ['id' => 'x']])
        ->assertHeader('X-Request-Id', 'req-12345678')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('renders domain exceptions in the error envelope', function () {
    $response = $this->getJson('/api/v1/_test/conflict')->assertStatus(409);

    expect($response->json())
        ->success->toBeFalse()
        ->error_code->toBe('INVALID_STATE_TRANSITION')
        ->message->toBe('This action is not allowed in the current status.')
        ->meta->toBe(['from' => 'paid'])
        ->request_id->toBe($response->headers->get('X-Request-Id'));
});

it('maps framework exceptions to stable codes', function () {
    $this->postJson('/api/v1/_test/validate')->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => ['name']]);
    $this->getJson('/api/v1/_test/auth')->assertStatus(401)->assertJsonPath('error_code', 'UNAUTHENTICATED');
    $this->getJson('/api/v1/_test/missing')->assertStatus(404)->assertJsonPath('error_code', 'NOT_FOUND');
});

it('checks permission expressions on routes', function () {
    $user = actingWith('invoices.write');

    $this->getJson('/api/v1/_test/access')->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN');

    grantPermissions($user, 'invoices.issue');
    $this->getJson('/api/v1/_test/access')->assertOk();
});

it('enforces the Idempotency-Key contract', function () {
    Sanctum::actingAs(User::factory()->create());
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/_test/pay', ['amount' => '1.000'])->assertStatus(428)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REQUIRED');

    $first = $this->postJson('/api/v1/_test/pay', ['amount' => '1.000'], ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/_test/pay', ['amount' => '1.000'], ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('n', $first->json('n'));
    $this->postJson('/api/v1/_test/pay', ['amount' => '2.000'], ['Idempotency-Key' => $key])
        ->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REUSED');
});
