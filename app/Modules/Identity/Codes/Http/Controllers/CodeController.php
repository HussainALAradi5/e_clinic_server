<?php

declare(strict_types=1);

namespace App\Modules\Identity\Codes\Http\Controllers;

use App\Enums\Identity\CodeSymbology;
use App\Modules\Identity\Codes\Contracts\CodeServiceInterface;
use App\Modules\Identity\Codes\Http\Requests\ScanCodeRequest;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class CodeController
{
    use ApiResponse;

    public function __construct(private readonly CodeServiceInterface $codes) {}

    /** Image of a record's code; `X-Code-Payload` carries the encoded text (for printing labels). */
    public function image(Request $request, string $entity, string $id): Response
    {
        $input = $request->validate([
            'symbology' => ['nullable', Rule::enum(CodeSymbology::class)],
            'size' => ['nullable', 'integer', 'between:64,1024'],
            'format' => ['nullable', 'in:svg,png'],
        ]);

        $image = $this->codes->image(
            $entity,
            $id,
            CodeSymbology::from((string) ($input['symbology'] ?? CodeSymbology::Qr->value)),
            (int) ($input['size'] ?? 256),
            (string) ($input['format'] ?? 'svg'),
        );

        return response($image->content, 200, [
            'Content-Type' => $image->mimeType,
            'Cache-Control' => 'private, max-age=86400',
            'X-Code-Payload' => $image->payload,
            'Access-Control-Expose-Headers' => 'X-Code-Payload',
        ]);
    }

    public function scan(ScanCodeRequest $request): JsonResponse
    {
        return $this->ok($this->codes->resolve($request->string('payload')->toString(), $request->purpose())->toArray());
    }

    public function verify(string $payload): JsonResponse
    {
        return $this->ok($this->codes->verify($payload)->toArray());
    }
}
