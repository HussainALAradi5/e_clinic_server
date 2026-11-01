<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Data;

use Illuminate\Http\UploadedFile;

/** What a member sends: text, files (or one recorded voice note), and the members it @mentions. */
final readonly class SendMessageData
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $mentions  user ids
     */
    public function __construct(
        public ?string $body,
        public array $files = [],
        public array $mentions = [],
        public bool $voice = false,
    ) {}
}
