<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

/**
 * The e-Clinic brand mark (pine tile, white cross, amber dot), as on the web and phone apps, for PDF letterheads.
 */
final class BrandMark
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 32 32">'
        .'<rect width="32" height="32" rx="9" fill="#0b5d57"/>'
        .'<path d="M13.2 7.5h5.6v5.7h5.7v5.6h-5.7v5.7h-5.6v-5.7H7.5v-5.6h5.7z" fill="#ffffff"/>'
        .'<circle cx="23.6" cy="8.4" r="2.4" fill="#f2b544"/></svg>';

    public static function dataUri(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::SVG);
    }
}
