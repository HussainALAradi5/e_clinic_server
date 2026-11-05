<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reference;

use App\Enums\Reference\ClinicImage;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Reference\ClinicImageRequest;
use App\Http\Requests\Reference\UpdateClinicProfileRequest;
use App\Http\Requests\Reference\UpdateOnlinePaymentSettingsRequest;
use App\Http\Resources\Reference\ClinicProfileResource;
use App\Http\Resources\Reference\OnlinePaymentSettingsResource;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The clinic profile: read by everyone (letterheads, the public site), changed by admins.
 */
final class ClinicProfileController
{
    use ApiResponse;

    public function __construct(private readonly ClinicProfileServiceInterface $profile) {}

    public function show(): JsonResponse
    {
        return $this->ok(new ClinicProfileResource($this->profile->current()));
    }

    public function update(UpdateClinicProfileRequest $request): JsonResponse
    {
        return $this->ok(new ClinicProfileResource($this->profile->update($request->toData())));
    }

    public function uploadImage(ClinicImageRequest $request, ClinicImage $image): JsonResponse
    {
        return $this->ok(new ClinicProfileResource($this->profile->setImage($image, $request->image())));
    }

    public function removeImage(ClinicImage $image): JsonResponse
    {
        return $this->ok(new ClinicProfileResource($this->profile->removeImage($image)));
    }

    /** Online payments: the switch, Tap settings and the addresses to register with Tap (admins). */
    public function onlinePayments(): JsonResponse
    {
        return $this->ok(new OnlinePaymentSettingsResource($this->profile->onlinePayments()));
    }

    public function updateOnlinePayments(UpdateOnlinePaymentSettingsRequest $request): JsonResponse
    {
        return $this->ok(new OnlinePaymentSettingsResource($this->profile->updateOnlinePayments($request->toData())));
    }

    /** The logo or stamp image (404 while none is set). */
    public function image(ClinicImage $image): Response
    {
        $file = $this->profile->image($image) ?? abort(404);

        return response($file['bytes'], 200, ['Content-Type' => $file['mime'], 'Cache-Control' => 'public, max-age=86400']);
    }
}
