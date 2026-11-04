<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\SignatureRequest;
use App\Interfaces\Identity\SignatureServiceInterface;
use App\Models\Identity\User;
use App\Services\Shared\FileStorage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The signed-in staff member's own signature (drawn or uploaded), printed on the documents they issue. */
final class SignatureController
{
    use ApiResponse;

    public function __construct(
        private readonly SignatureServiceInterface $signatures,
        private readonly FileStorage $files,
    ) {}

    /** The signature image (404 while there is none). */
    public function show(Request $request): Response
    {
        $signature = $this->signatures->of($this->me($request)) ?? abort(404);

        return response((string) $this->files->disk()->get($signature->path), 200, [
            'Content-Type' => $signature->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function update(SignatureRequest $request): JsonResponse
    {
        $signature = $this->signatures->set($this->me($request), $request->signature());

        return $this->ok(['has_signature' => true, 'updated_at' => $signature->created_at->toIso8601String()]);
    }

    public function destroy(Request $request): Response
    {
        $this->signatures->remove($this->me($request));

        return $this->noContent();
    }

    private function me(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
