<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Controllers;

use App\Modules\Identity\Users\Models\User;
use App\Modules\Identity\Users\Support\Avatar;
use App\Shared\Files\FileStorage;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The signed-in user's own profile photo (shown in chat and the top bar). */
final class ProfilePhotoController
{
    use ApiResponse;

    public function __construct(private readonly FileStorage $files) {}

    public function update(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096']]);
        $person = $this->me($request)->person;
        $old = $person->photo_path;

        $person->forceFill(['photo_path' => $this->files->storePrivate($request->file('photo'), 'avatars')])->save();
        $this->files->delete($old);

        return $this->ok(['avatar_url' => Avatar::url($person)]);
    }

    public function destroy(Request $request): Response
    {
        $person = $this->me($request)->person;
        $this->files->delete($person->photo_path);
        $person->forceFill(['photo_path' => null])->save();

        return $this->noContent();
    }

    private function me(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
