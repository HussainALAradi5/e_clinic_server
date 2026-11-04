<?php

declare(strict_types=1);

namespace App\Utilities\Demo;

use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Enums\Shared\GenericStatus;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Identity\Attachment;
use App\Services\Shared\FileStorage;

/**
 * Gives completed demo imaging and ECG results their pictures: one synthetic image (ImagingImageGenerator) attached to
 * every completed X-ray, ultrasound, CT, MRI or ECG order that has none yet. Development and demo data only.
 */
final class ImagingDemoImages
{
    private const TYPES = [ClinicalOrderType::Xray, ClinicalOrderType::Ultrasound, ClinicalOrderType::Ct, ClinicalOrderType::Mri, ClinicalOrderType::Ecg];

    public function __construct(
        private readonly ImagingImageGenerator $generator,
        private readonly FileStorage $files,
    ) {}

    /** Attaches the missing images; returns how many were added. */
    public function attachMissing(): int
    {
        $added = 0;
        ClinicalOrder::query()
            ->whereIn('type', array_map(fn (ClinicalOrderType $t): string => $t->value, self::TYPES))
            ->where('status', GenericStatus::Completed->value)
            ->whereDoesntHave('attachments')
            ->each(function (ClinicalOrder $order) use (&$added): void {
                $bytes = $this->generator->png($order->type, $order->code, $order->is_abnormal === true);
                if ($bytes !== null) {
                    $this->attach($order, $bytes);
                    $added++;
                }
            });

        return $added;
    }

    private function attach(ClinicalOrder $order, string $bytes): void
    {
        $path = $this->files->newPath('attachments', 'png');
        $this->files->disk()->put($path, $bytes);
        (new Attachment)->forceFill([
            'attachable_type' => 'clinical_order',
            'attachable_id' => $order->id,
            'category' => AttachmentCategory::XrayImage,
            'original_name' => strtolower($order->type->value).'-'.$order->code.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($bytes),
            'disk' => $this->files->diskName(),
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'scan_status' => FileScanStatus::Clean,
            'is_sensitive' => true,
            'description' => $order->type->label().' · '.($order->body_part ?? $order->code).' (demo image)',
        ])->save();
    }
}
