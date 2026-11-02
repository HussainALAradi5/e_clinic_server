<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

/**
 * 2FA enrolment: the authenticator QR code and its otpauth:// URI (the secret is never returned again).
 */
final readonly class TwoFactorSetupData
{
    public function __construct(
        public string $qrCodeSvg,
        public string $otpauthUrl,
    ) {}

    /**
     * @return array{qr_code_svg: string, otpauth_url: string}
     */
    public function toArray(): array
    {
        return ['qr_code_svg' => $this->qrCodeSvg, 'otpauth_url' => $this->otpauthUrl];
    }
}
