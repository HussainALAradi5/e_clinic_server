<?php

declare(strict_types=1);

namespace App\Shared\Data;

use Symfony\Component\HttpFoundation\Response;

/**
 * Response stored by EnsureIdempotency for replay (cached 24 h).
 */
final readonly class IdempotentResponse
{
    public function __construct(
        public string $fingerprint,
        public int $status,
        public string $content,
        public ?string $contentType,
    ) {}

    public static function capture(string $fingerprint, Response $response): self
    {
        return new self($fingerprint, $response->getStatusCode(), (string) $response->getContent(), $response->headers->get('Content-Type'));
    }

    public function toResponse(string $replayedHeader): Response
    {
        return new Response($this->content, $this->status, array_filter([
            'Content-Type' => $this->contentType,
            $replayedHeader => 'true',
        ]));
    }
}
