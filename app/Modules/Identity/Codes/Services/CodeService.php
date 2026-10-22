<?php

declare(strict_types=1);

namespace App\Modules\Identity\Codes\Services;

use App\Enums\Identity\CodeSymbology;
use App\Enums\Identity\ScanPurpose;
use App\Modules\Identity\Codes\Contracts\CodeServiceInterface;
use App\Modules\Identity\Codes\Data\CodeImage;
use App\Modules\Identity\Codes\Data\ScanResultData;
use App\Modules\Identity\Codes\Exceptions\InvalidScanCodeException;
use App\Shared\Access\PermissionExpression;
use App\Shared\Support\MoneyHelper;
use BackedEnum;
use Brick\Money\Money;
use Com\Tecnick\Barcode\Barcode;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

final class CodeService implements CodeServiceInterface
{
    private const PATTERN = '/^([a-z_]{1,40}):([A-Za-z0-9\-]{1,40})\.([a-f0-9]{6,64})$/';

    public function image(string $entityAlias, string $id, CodeSymbology $symbology, int $size = 256, string $format = 'svg'): CodeImage
    {
        $record = $this->readable($entityAlias, fn ($q) => $q->whereKey($id));
        $payload = $this->payloadFor($entityAlias, (string) $record->getAttribute('code'));
        $key = "code-image:{$symbology->value}:{$size}:{$format}:".hash('sha256', $payload);

        /** @var string $content */
        $content = Cache::remember($key, now()->addHours(Config::integer('codes.image_cache_hours', 24)), function () use ($payload, $symbology, $size, $format): string {
            [$type, $height] = match ($symbology) {
                CodeSymbology::Qr => ['QRCODE,M', $size],
                CodeSymbology::Datamatrix => ['DATAMATRIX', $size],
                CodeSymbology::Code128 => ['C128', max(32, intdiv($size, 3))],
            };
            $barcode = (new Barcode)->getBarcodeObj($type, $payload, $size, $height, 'black', [-2, -2, -2, -2])->setBackgroundColor('white');

            return base64_encode($format === 'png' ? $barcode->getPngData(false) : $barcode->getSvgCode());
        });

        return new CodeImage((string) base64_decode($content, true), $format === 'png' ? 'image/png' : 'image/svg+xml', $payload);
    }

    public function payloadFor(string $entityAlias, string $code): string
    {
        return "{$entityAlias}:{$code}.".$this->signature($entityAlias, $code);
    }

    public function resolve(string $payload, ScanPurpose $purpose): ScanResultData
    {
        [$alias, $code] = $this->parse($payload);
        $record = $this->readable($alias, fn ($q) => $q->where('code', $code));

        return new ScanResultData($alias, (string) $record->getKey(), $code, $this->summary($record, (array) Config::get("codes.entities.{$alias}.summary", ['code'])));
    }

    public function verify(string $payload): ScanResultData
    {
        [$alias, $code] = $this->parse($payload);
        $fields = Config::get("codes.entities.{$alias}.public");
        if (! is_array($fields)) {
            throw new InvalidScanCodeException;
        }

        /** @var class-string<Model> $model */
        $model = Config::string("codes.entities.{$alias}.model");
        $record = $model::query()->where('code', $code)->firstOrFail();

        return new ScanResultData($alias, (string) $record->getKey(), $code, $this->summary($record, $fields));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parse(string $payload): array
    {
        if (preg_match(self::PATTERN, trim($payload), $m) !== 1
            || ! is_array(Config::get("codes.entities.{$m[1]}"))
            || ! hash_equals($this->signature($m[1], $m[2]), $m[3])) {
            throw new InvalidScanCodeException;
        }

        return [$m[1], $m[2]];
    }

    private function signature(string $alias, string $code): string
    {
        return substr(hash_hmac('sha256', "{$alias}:{$code}", 'codes|'.Config::string('app.key')), 0, Config::integer('codes.signature_length', 12));
    }

    /**
     * Loads a record of a whitelisted entity the current user can read (404 otherwise — no existence leak).
     *
     * @param  callable(Builder<Model>): mixed  $where
     */
    private function readable(string $alias, callable $where): Model
    {
        /** @var array{model: class-string<Model>, resource: string}|null $entity */
        $entity = Config::get("codes.entities.{$alias}");
        if (! is_array($entity)) {
            throw (new ModelNotFoundException)->setModel(Model::class);
        }

        $query = $entity['model']::query();
        $where($query);
        $record = $query->firstOrFail();
        $r = $entity['resource'];

        if (! PermissionExpression::parse("{$r}.read_all|{$r}.read_own")->evaluate(Auth::user(), $record)) {
            throw (new ModelNotFoundException)->setModel($entity['model']);
        }

        return $record;
    }

    /**
     * @param  array<mixed>  $fields  attribute names from config/codes.php
     * @return array<string, mixed>
     */
    private function summary(Model $record, array $fields): array
    {
        $summary = [];
        foreach (array_filter($fields, is_string(...)) as $field) {
            $value = $record->getAttribute($field);
            $summary[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof Money => MoneyHelper::format($value),
                $value instanceof DateTimeInterface => $value->format($record->hasCast($field, ['date', 'immutable_date']) ? 'Y-m-d' : DateTimeInterface::ATOM),
                default => $value,
            };
        }

        return $summary;
    }
}
