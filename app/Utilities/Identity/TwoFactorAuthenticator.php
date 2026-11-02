<?php

declare(strict_types=1);

namespace App\Utilities\Identity;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * TOTP (RFC 6238) via pragmarx/google2fa: 160-bit secrets, ±1 window verification,
 * otpauth:// URI with an SVG QR code, and one-time recovery codes.
 */
final class TwoFactorAuthenticator
{
    private const SECRET_LENGTH = 32; // base32 chars = 160 bits

    private const WINDOW = 1;

    private const QR_SIZE = 192;

    public function __construct(private readonly Google2FA $engine = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(self::SECRET_LENGTH);
    }

    public function verify(string $secret, string $code): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        try {
            return $this->engine->verifyKey($secret, $code, self::WINDOW) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** Current code (tests and tooling). */
    public function currentCode(string $secret): string
    {
        return $this->engine->getCurrentOtp($secret);
    }

    public function otpauthUrl(string $holder, string $secret): string
    {
        return $this->engine->getQRCodeUrl(Config::string('app.name'), $holder, $secret);
    }

    public function qrCodeSvg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE, 1), new SvgImageBackEnd)))->writeString($url);

        // Drop the XML declaration so the markup can be inlined in HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }

    /**
     * @return list<string> e.g. `k3f9q-2mzx7`
     */
    public function recoveryCodes(): array
    {
        return array_map(
            fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, Config::integer('clinic.auth.recovery_codes')),
        );
    }
}
