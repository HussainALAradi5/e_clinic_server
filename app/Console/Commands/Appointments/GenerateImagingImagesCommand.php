<?php

declare(strict_types=1);

namespace App\Console\Commands\Appointments;

use App\Utilities\Demo\ImagingDemoImages;
use Illuminate\Console\Command;

/** `php artisan demo:imaging-images` — draws labelled demo images for completed imaging and ECG results without any. */
final class GenerateImagingImagesCommand extends Command
{
    protected $signature = 'demo:imaging-images';

    protected $description = 'Attach synthetic demo images (X-ray, ultrasound, CT, MRI, ECG) to completed results that have none';

    public function handle(ImagingDemoImages $images): int
    {
        if (app()->isProduction()) {
            $this->error('Demo images are for development and demo data only.');

            return self::FAILURE;
        }
        $this->info($images->attachMissing().' demo images attached.');

        return self::SUCCESS;
    }
}
