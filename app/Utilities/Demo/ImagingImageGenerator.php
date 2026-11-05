<?php

declare(strict_types=1);

namespace App\Utilities\Demo;

use App\Enums\Appointments\ClinicalOrderType;
use GdImage;
use RuntimeException;

/**
 * Draws synthetic, clearly labelled demo images for imaging and ECG results (no real patient images ship with the
 * project): a chest radiograph, an ultrasound fan, a CT / MRI axial slice, a 12-lead-style ECG strip. Drawn locally
 * with GD — no external service, so nothing about a patient leaves the server. Seeded per order, so the same order
 * always gets the same picture.
 */
final class ImagingImageGenerator
{
    private const WIDTH = 900;

    private const HEIGHT = 700;

    private const LABEL = 'SYNTHETIC DEMO IMAGE - NOT FOR DIAGNOSIS';

    /** PNG bytes for the order type, or null for types without images (lab tests, referrals). */
    public function png(ClinicalOrderType $type, string $seed, bool $abnormal = false): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The GD extension is required to draw demo images.');
        }
        mt_srand(crc32($seed));

        $image = match ($type) {
            ClinicalOrderType::Xray => $this->chestXray($abnormal),
            ClinicalOrderType::Ultrasound => $this->ultrasound(),
            ClinicalOrderType::Ct, ClinicalOrderType::Mri => $this->axialSlice($type === ClinicalOrderType::Mri),
            ClinicalOrderType::Ecg => $this->ecg($abnormal),
            ClinicalOrderType::LabTest, ClinicalOrderType::Referral => null,
        };
        if ($image === null) {
            return null;
        }

        $this->label($image, $type->label());
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * @param  int<0, 255>  $r
     * @param  int<0, 255>  $g
     * @param  int<0, 255>  $b
     */
    private function canvas(int $r = 0, int $g = 0, int $b = 0): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT) ?: throw new RuntimeException('Cannot draw.');
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        return $image;
    }

    private function grey(GdImage $image, int $level, int $alpha = 0): int
    {
        $level = max(0, min(255, $level));

        return (int) imagecolorallocatealpha($image, $level, $level, $level, max(0, min(127, $alpha)));
    }

    /** PA chest film: soft tissue, dark lung fields, spine, ribs, heart shadow, clavicles; a hazy patch when abnormal. */
    private function chestXray(bool $abnormal): GdImage
    {
        $image = $this->canvas(8, 8, 10);
        $cx = self::WIDTH / 2;
        for ($i = 0; $i < 18; $i++) {
            imagefilledellipse($image, (int) $cx, 380, 760 - $i * 18, 660 - $i * 12, $this->grey($image, 40 + $i * 3, 60));
        }
        foreach ([-1, 1] as $side) {
            for ($i = 0; $i < 10; $i++) {
                imagefilledellipse($image, (int) ($cx + $side * 165), 360, 250 - $i * 10, 420 - $i * 12, $this->grey($image, 22 - $i, 40));
            }
        }
        imagesetthickness($image, 5);
        foreach ([-1, 1] as $side) {
            for ($rib = 0; $rib < 9; $rib++) {
                $y = 190 + $rib * 42;
                imagearc($image, (int) ($cx + $side * 150), $y + 70, 330, 150, $side === 1 ? 200 : 260, $side === 1 ? 280 : 340, $this->grey($image, 150 + mt_rand(-12, 12), 50));
            }
            imageline($image, (int) $cx, 170, (int) ($cx + $side * 230), 150, $this->grey($image, 185, 30));
        }
        for ($v = 0; $v < 14; $v++) {
            imagefilledrectangle($image, (int) $cx - 22, 120 + $v * 38, (int) $cx + 22, 150 + $v * 38, $this->grey($image, 170, 35));
        }
        imagefilledellipse($image, (int) $cx + 45, 470, 280, 230, $this->grey($image, 120, 45));
        if ($abnormal) {
            for ($i = 0; $i < 12; $i++) {
                imagefilledellipse($image, (int) $cx - 170 + mt_rand(-20, 20), 500 + mt_rand(-15, 15), 150 - $i * 8, 110 - $i * 6, $this->grey($image, 150, 95));
            }
        }
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);

        return $image;
    }

    /** B-mode ultrasound: a fan of speckle with a dark oval structure. */
    private function ultrasound(): GdImage
    {
        $image = $this->canvas();
        $apexX = self::WIDTH / 2;
        for ($n = 0; $n < 26000; $n++) {
            $angle = deg2rad(mt_rand(-3800, 3800) / 100);
            $depth = mt_rand(40, 620);
            $x = (int) ($apexX + sin($angle) * $depth);
            $y = (int) (40 + cos($angle) * $depth);
            imagesetpixel($image, $x, $y, $this->grey($image, mt_rand(40, 200) - (int) ($depth / 8)));
        }
        imagefilledellipse($image, (int) $apexX + 30, 360, 230, 140, $this->grey($image, 10, 20));
        imagefilter($image, IMG_FILTER_SMOOTH, 4);

        return $image;
    }

    /** Axial head / abdomen slice: concentric densities, darker for MRI fluid, brighter rim for CT bone. */
    private function axialSlice(bool $mri): GdImage
    {
        $image = $this->canvas();
        $cx = self::WIDTH / 2;
        imagefilledellipse($image, (int) $cx, 350, 560, 620, $this->grey($image, $mri ? 120 : 235));
        imagefilledellipse($image, (int) $cx, 350, 520, 580, $this->grey($image, $mri ? 70 : 95));
        for ($i = 0; $i < 9; $i++) {
            imagefilledellipse($image, (int) $cx + mt_rand(-120, 120), 350 + mt_rand(-150, 150), mt_rand(40, 140), mt_rand(40, 120), $this->grey($image, $mri ? mt_rand(130, 210) : mt_rand(60, 130), 40));
        }
        imagefilledellipse($image, (int) $cx - 40, 340, 40, 120, $this->grey($image, $mri ? 230 : 30, 30));
        imagefilledellipse($image, (int) $cx + 40, 340, 40, 120, $this->grey($image, $mri ? 230 : 30, 30));
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);

        return $image;
    }

    /** ECG strip: pink grid and a sinus rhythm trace (ST elevation when abnormal). */
    private function ecg(bool $abnormal): GdImage
    {
        $image = $this->canvas(255, 247, 247);
        $minor = (int) imagecolorallocate($image, 250, 210, 210);
        $major = (int) imagecolorallocate($image, 240, 160, 160);
        for ($x = 0; $x < self::WIDTH; $x += 10) {
            imageline($image, $x, 0, $x, self::HEIGHT, $x % 50 === 0 ? $major : $minor);
        }
        for ($y = 0; $y < self::HEIGHT; $y += 10) {
            imageline($image, 0, $y, self::WIDTH, $y, $y % 50 === 0 ? $major : $minor);
        }
        $ink = (int) imagecolorallocate($image, 20, 20, 30);
        imagesetthickness($image, 2);
        foreach ([150, 350, 550] as $baseline) {
            $previous = null;
            for ($x = 0; $x < self::WIDTH; $x++) {
                $y = $baseline - $this->beat(($x % 160) / 160, $abnormal) * 70;
                if ($previous !== null) {
                    imageline($image, $x - 1, (int) $previous, $x, (int) $y, $ink);
                }
                $previous = $y;
            }
        }

        return $image;
    }

    /** One heartbeat (0–1 of the cycle): P wave, QRS complex, T wave. */
    private function beat(float $t, bool $stElevation): float
    {
        return 0.15 * exp(-(($t - 0.18) ** 2) / 0.0008)
            - 0.15 * exp(-(($t - 0.36) ** 2) / 0.00005)
            + 1.0 * exp(-(($t - 0.39) ** 2) / 0.00008)
            - 0.25 * exp(-(($t - 0.42) ** 2) / 0.00006)
            + ($stElevation ? 0.25 * exp(-(($t - 0.5) ** 2) / 0.004) : 0)
            + 0.3 * exp(-(($t - 0.62) ** 2) / 0.002);
    }

    private function label(GdImage $image, string $type): void
    {
        $band = (int) imagecolorallocatealpha($image, 0, 0, 0, 50);
        $text = (int) imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, self::HEIGHT - 34, self::WIDTH, self::HEIGHT, $band);
        imagestring($image, 4, 14, self::HEIGHT - 26, strtoupper($type).' - '.self::LABEL, $text);
    }
}
