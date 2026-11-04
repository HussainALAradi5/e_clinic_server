<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\DocumentPage;
use App\Dtos\Documents\RenderedFile;
use App\Enums\Documents\DocumentLayout;
use App\Enums\Documents\DocumentType;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\CodeSymbology;
use App\Enums\Identity\FileScanStatus;
use App\Enums\Reference\ClinicImage;
use App\Interfaces\Documents\DocumentBlock;
use App\Interfaces\Documents\DocumentBuilder;
use App\Interfaces\Documents\DocumentServiceInterface;
use App\Interfaces\Identity\CodeServiceInterface;
use App\Interfaces\Identity\SignatureServiceInterface;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Interfaces\Shared\ReleasedToPatient;
use App\Models\Identity\Attachment;
use App\Models\Identity\GeneratedDocument;
use App\Models\Identity\User;
use App\Services\Shared\FileStorage;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Documents\BrandMark;
use App\Utilities\Documents\DocumentFormat;
use App\Utilities\Documents\PdfRenderer;
use App\Utilities\Shared\DocumentNumberGenerator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One service for every document type: loads the record, checks the reader may see it (same rights as the record; a
 * patient sees a result only once released), and returns its PDF. The PDF is stored once per version of the record
 * as a GeneratedDocument (own DOC code, unique file name, file kept as an attachment); while the record is unchanged
 * the same reader gets the stored file instead of drawing it again (the PDF names who printed it). The letterhead is
 * the clinic profile (name, logo, contacts).
 */
final class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly CodeServiceInterface $codes,
        private readonly DocumentFormat $format,
        private readonly FileStorage $files,
        private readonly DocumentNumberGenerator $numbers,
        private readonly ClinicProfileServiceInterface $profile,
        private readonly SignatureServiceInterface $signatures,
    ) {}

    public function generate(DocumentType $type, string $id): GeneratedDocument
    {
        /** @var DocumentBuilder $builder */
        $builder = app($type->builder());
        $record = $type->model()::query()->with($builder->with())->findOrFail($id);
        $this->authorize($type, $record, Auth::user());

        return $this->latest($type, $record) ?? $this->store($type, $record, $builder);
    }

    public function pdf(DocumentType $type, string $id): RenderedFile
    {
        $document = $this->generate($type, $id);

        return new RenderedFile((string) $this->files->disk()->get($document->attachment->path), $document->file_name);
    }

    /**
     * Variables of the shared letterhead (layouts.paper), also used by list exports and reports.
     *
     * @return array<string, mixed>
     */
    public function letterhead(string $title, string $reference): array
    {
        $profile = $this->profile->current();
        /** @var User|null $user */
        $user = Auth::user();

        return [
            'clinic' => $profile->only(['name', 'tagline', 'address', 'phone', 'email', 'website']),
            'logo' => $this->clinicImage(ClinicImage::Logo) ?? BrandMark::dataUri(),
            'colors' => Config::array('documents.colors'),
            'title' => $title,
            'reference' => $reference,
            'generatedAt' => $this->format->now(),
            'printedBy' => $user?->person->full_name,
        ];
    }

    /** The stored PDF of this version of the record printed by this reader (it names them), if its file is still there. */
    private function latest(DocumentType $type, Model $record): ?GeneratedDocument
    {
        $document = GeneratedDocument::query()->with('attachment')
            ->where('type', $type->value)
            ->where('record_id', $record->getKey())
            ->where('created_by', Auth::id())
            ->where('is_deleted', false)
            ->latest('created_at')
            ->first();
        $updated = $record->getAttribute('updated_at');

        $current = $document !== null && $updated !== null && $document->record_updated_at?->equalTo($updated) === true;

        return $current && $this->files->disk()->exists($document->attachment->path) ? $document : null;
    }

    private function store(DocumentType $type, Model $record, DocumentBuilder $builder): GeneratedDocument
    {
        $page = $this->withSignatures($builder->build($record));
        $bytes = $this->renderer->render(DocumentLayout::Document, [
            ...$this->letterhead($page->title, $page->reference),
            'page' => $page,
            'qr' => $this->code($type, (string) $record->getKey(), CodeSymbology::Qr),
            'barcode' => $this->code($type, (string) $record->getKey(), CodeSymbology::Code128),
        ]);

        return DB::transaction(function () use ($type, $record, $page, $bytes): GeneratedDocument {
            $id = (string) Str::uuid7();
            $code = $this->numbers->next('generated_document', GeneratedDocument::codePrefix(), yearly: true);
            $fileName = Str::slug($type->label()).'_'.$page->reference.'_'.$code.'.pdf';
            $path = $this->files->newPath('documents', 'pdf');
            $this->files->disk()->put($path, $bytes);

            $attachment = (new Attachment)->forceFill([
                'attachable_type' => 'generated_document',
                'attachable_id' => $id,
                'category' => AttachmentCategory::GeneratedDocument,
                'original_name' => $fileName,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'disk' => $this->files->diskName(),
                'path' => $path,
                'sha256' => hash('sha256', $bytes),
                'scan_status' => FileScanStatus::Clean,
                'is_sensitive' => true,
                'description' => $page->title.' '.$page->reference,
            ]);
            $attachment->save();

            $document = (new GeneratedDocument)->forceFill([
                'id' => $id,
                'code' => $code,
                'type' => $type,
                'record_id' => $record->getKey(),
                'reference' => $page->reference,
                'file_name' => $fileName,
                'attachment_id' => $attachment->id,
                'record_updated_at' => $record->getAttribute('updated_at'),
                'size_bytes' => strlen($bytes),
            ]);
            $document->save();

            return $document->setRelation('attachment', $attachment);
        });
    }

    /** 404 (not 403) when the reader may not see the record, so its existence does not leak. */
    private function authorize(DocumentType $type, Model $record, ?Authenticatable $user): void
    {
        $r = $type->resource();
        $allowed = $user !== null && PermissionExpression::parse("{$r}.read_all|{$r}.read_own")->evaluate($user, $record);
        $unreleased = $record instanceof ReleasedToPatient && $user !== null
            && $record->getAttribute('patient_id') === $user->getAuthIdentifier() && ! $record->isReleasedTo($user);

        if (! $allowed || $unreleased) {
            throw (new ModelNotFoundException)->setModel($type->model());
        }
    }

    /** The record's signed QR (a check link any camera opens) or reference barcode, as a PNG data URI. */
    private function code(DocumentType $type, string $id, CodeSymbology $symbology): ?string
    {
        $entity = $type->codeEntity();
        if ($entity === null) {
            return null;
        }
        $pixels = Config::integer($symbology === CodeSymbology::Code128 ? 'documents.barcode_pixels' : 'documents.qr_pixels');
        $image = $this->codes->image($entity, $id, $symbology, $pixels, 'png');

        return 'data:'.$image->mimeType.';base64,'.base64_encode($image->content);
    }

    /** Fills each signature block with its signer's own signature and, where asked, the clinic stamp. */
    private function withSignatures(DocumentPage $page): DocumentPage
    {
        $stamp = $this->clinicImage(ClinicImage::Stamp);
        $blocks = array_map(fn (DocumentBlock $block): DocumentBlock => $block instanceof SignatureBlock
            ? $block->withImages($block->signerId !== null ? $this->signatures->dataUri($block->signerId) : null, $stamp)
            : $block, $page->blocks);

        return new DocumentPage($page->title, $page->reference, $page->fileName, $page->badges, $page->highlights, $blocks, $page->verifyNote, $page->issuedBy);
    }

    private function clinicImage(ClinicImage $image): ?string
    {
        $file = $this->profile->image($image);

        return $file === null ? null : 'data:'.$file['mime'].';base64,'.base64_encode($file['bytes']);
    }
}
