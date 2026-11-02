<?php

namespace Database\Seeders;

use App\Enums\Communication\ConversationType;
use App\Enums\Communication\MessageKind;
use App\Enums\Communication\ParticipantRole;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\Communication\MessageReaction;
use App\Models\Identity\Attachment;
use App\Models\Identity\Person;
use App\Services\Shared\FileStorage;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo staff chat: three groups (each with a leader), one-to-one chats, @mentions, reactions, a photo, a PDF and a
 * voice note, profile photos for a few people, and unread messages waiting for Dr Omar. Never in production.
 */
final class ChatDemoSeeder extends Seeder
{
    private const GROUP_WARD = 'Ward 3 — Morning team';

    /** @var array<string, string> email prefix => user id */
    private array $ids = [];

    public function __construct(private readonly FileStorage $files) {}

    public function run(ClinicClock $clock): void
    {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Conversation::query()->where('title', self::GROUP_WARD)->exists()) {
            return;
        }
        $this->ids = Person::query()
            ->where('email', 'like', '%'.DemoSeeder::EMAIL_DOMAIN)
            ->pluck('id', 'email')
            ->mapWithKeys(fn (string $id, string $email): array => [str_replace(DemoSeeder::EMAIL_DOMAIN, '', $email) => $id])
            ->all();
        if (! isset($this->ids['doctor'], $this->ids['doctor2'], $this->ids['nurse'], $this->ids['reception'], $this->ids['pharmacist'])) {
            return;
        }

        // Clinic-local wall-clock times (stored as UTC).
        $today = $clock->today();
        $yesterday = $today->subDay();
        $this->photos();

        // Ward 3: Dr Omar leads; a photo, a PDF, mentions and reactions; two messages Omar has not read yet.
        $ward = $this->group(self::GROUP_WARD, 'doctor', ['doctor2', 'nurse', 'nurse2', 'reception']);
        $this->say($ward, 'doctor', $yesterday->setTime(7, 45), 'Good morning team ☀️ Rounds start at 8:30, bed 4 first please.');
        $this->say($ward, 'nurse', $yesterday->setTime(7, 49), 'Noted. Bed 4 vitals are stable overnight.', react: ['doctor' => '👍', 'doctor2' => '👍']);
        $this->say($ward, 'doctor2', $yesterday->setTime(7, 52), '@Omar Khalil I will join after my 8:00 consult.', mentions: ['doctor']);
        $chart = $this->say($ward, 'nurse2', $yesterday->setTime(10, 15), 'Physio plan for bed 6 attached.', kind: MessageKind::Media);
        $this->attach($chart, 'physio-plan-bed6.pdf', 'application/pdf', $this->pdf(), AttachmentCategory::ChatMedia);
        $this->say($ward, 'doctor', $yesterday->setTime(10, 20), 'Thanks @Grace Dela Cruz — looks good, go ahead.', mentions: ['nurse2'], react: ['nurse2' => '❤️']);
        $photo = $this->say($ward, 'nurse', $today->setTime(8, 5), 'Wound dressing changed, healing well.', kind: MessageKind::Media);
        $this->attach($photo, 'dressing-bed4.png', 'image/png', $this->image(640, 420, [34, 139, 120], [219, 234, 254]), AttachmentCategory::ChatMedia);
        $this->say($ward, 'reception', $today->setTime(8, 40), 'Mr Ali’s family is at the desk asking for an update.', react: ['nurse' => '🙏']);
        $this->readUpTo($ward, $today->setTime(8, 6), ['doctor']);

        // Pharmacy & front desk: Yusuf leads; a voice note and a mention of Dr Omar.
        $pharmacy = $this->group('Pharmacy & front desk', 'pharmacist', ['reception', 'doctor', 'cashier']);
        $this->say($pharmacy, 'pharmacist', $yesterday->setTime(12, 10), 'Amoxicillin 500 mg back in stock (batch AMX-24).', react: ['reception' => '👍', 'cashier' => '👍', 'doctor' => '😮']);
        $this->say($pharmacy, 'cashier', $yesterday->setTime(12, 30), 'Great, two patients were waiting for it.');
        $voice = $this->say($pharmacy, 'reception', $today->setTime(9, 2), null, kind: MessageKind::Voice);
        $this->attach($voice, 'voice-note.wav', 'audio/wav', $this->tone(), AttachmentCategory::VoiceNote);
        $this->say($pharmacy, 'pharmacist', $today->setTime(9, 10), '@Omar Khalil can you confirm the dose on PRS-0042?', mentions: ['doctor']);
        $this->readUpTo($pharmacy, $yesterday->setTime(12, 31), ['doctor']);

        // Clinic admin: Amal leads; Dr Omar is a member.
        if (isset($this->ids['admin'], $this->ids['hr'], $this->ids['accountant'])) {
            $admin = $this->group('Clinic admin', 'admin', ['hr', 'accountant', 'doctor']);
            $this->say($admin, 'admin', $yesterday->setTime(16, 0), 'Reminder: monthly staff meeting on Thursday at 2 pm.');
            $this->say($admin, 'hr', $yesterday->setTime(16, 5), 'Payroll cut-off is the 25th, please approve overtime before then.', react: ['admin' => '👍']);
            $this->say($admin, 'accountant', $yesterday->setTime(16, 20), 'October expenses are posted.');
            $this->readUpTo($admin, $yesterday->setTime(16, 30), ['doctor']);
        }

        // One-to-one chats of Dr Omar.
        $sara = $this->direct('doctor', 'doctor2');
        $this->say($sara, 'doctor2', $yesterday->setTime(18, 2), 'Could you cover my 4 pm slot tomorrow?');
        $this->say($sara, 'doctor', $yesterday->setTime(18, 10), 'Sure, no problem.', react: ['doctor2' => '🙏']);
        $this->say($sara, 'doctor2', $yesterday->setTime(18, 11), 'Thank you! I owe you a coffee ☕', react: ['doctor' => '😂']);
        $this->readUpTo($sara, $yesterday->setTime(18, 12), ['doctor', 'doctor2']);

        $rana = $this->direct('doctor', 'reception');
        $this->say($rana, 'reception', $today->setTime(8, 55), 'Your 9:30 patient called, running 10 minutes late.');
        $this->say($rana, 'reception', $today->setTime(8, 56), 'Should I move the 9:45 forward?');

        $yusuf = $this->direct('doctor', 'pharmacist');
        $this->say($yusuf, 'doctor', $yesterday->setTime(11, 0), 'Is there a generic for Lipitor in stock?');
        $this->say($yusuf, 'pharmacist', $yesterday->setTime(11, 4), 'Yes — atorvastatin 20 mg, plenty.');
        $this->readUpTo($yusuf, $yesterday->setTime(11, 5), ['doctor', 'pharmacist']);
    }

    /**
     * @param  list<string>  $members  email prefixes
     */
    private function group(string $title, string $leader, array $members): Conversation
    {
        $chat = Conversation::query()->create(['type' => ConversationType::Group, 'title' => $title, 'created_by' => $this->ids[$leader]]);
        $chat->participants()->create(['user_id' => $this->ids[$leader], 'role' => ParticipantRole::Admin]);
        foreach ($members as $member) {
            if (isset($this->ids[$member])) {
                $chat->participants()->create(['user_id' => $this->ids[$member], 'role' => ParticipantRole::Member]);
            }
        }

        return $chat;
    }

    private function direct(string $a, string $b): Conversation
    {
        $chat = Conversation::query()->create([
            'type' => ConversationType::Direct,
            'direct_key' => Conversation::directKey($this->ids[$a], $this->ids[$b]),
            'created_by' => $this->ids[$a],
        ]);
        foreach ([$a, $b] as $who) {
            $chat->participants()->create(['user_id' => $this->ids[$who], 'role' => ParticipantRole::Member]);
        }

        return $chat;
    }

    /**
     * @param  list<string>  $mentions  email prefixes
     * @param  array<string, string>  $react  email prefix => emoji
     */
    private function say(Conversation $chat, string $from, CarbonImmutable $at, ?string $body, MessageKind $kind = MessageKind::Text, array $mentions = [], array $react = []): Message
    {
        $message = new Message([
            'conversation_id' => $chat->id,
            'sender_id' => $this->ids[$from],
            'body' => $body,
            'kind' => $kind,
            'mentions' => $mentions === [] ? null : array_map(fn (string $p): string => $this->ids[$p], $mentions),
        ]);
        $at = $at->utc();
        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        foreach ($react as $who => $emoji) {
            MessageReaction::query()->create(['message_id' => $message->id, 'user_id' => $this->ids[$who], 'emoji' => $emoji]);
        }
        $chat->forceFill(['last_message_at' => $at])->save();
        // The sender has read their own chat up to their message.
        $chat->participants()->where('user_id', $this->ids[$from])->update(['last_read_at' => $at]);

        return $message;
    }

    /**
     * Everyone listed read the chat up to `$at`; everyone else read all of it.
     *
     * @param  list<string>  $who  email prefixes
     */
    private function readUpTo(Conversation $chat, CarbonImmutable $at, array $who): void
    {
        $at = $at->utc();
        $ids = array_map(fn (string $p): string => $this->ids[$p], $who);
        $chat->participants()->whereIn('user_id', $ids)->update(['last_read_at' => $at]);
        $chat->participants()->whereNotIn('user_id', $ids)->update(['last_read_at' => $chat->last_message_at]);
    }

    private function attach(Message $message, string $name, string $mime, string $bytes, AttachmentCategory $category): void
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $path = $this->files->newPath('attachments', $extension);
        $this->files->disk()->put($path, $bytes);
        (new Attachment)->forceFill([
            'attachable_type' => 'chat_message',
            'attachable_id' => $message->id,
            'category' => $category,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'disk' => $this->files->diskName(),
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'scan_status' => FileScanStatus::Clean,
            'is_sensitive' => false,
        ])->save();
    }

    /** Profile photos (soft abstract portraits) for a few people; the rest show initials. */
    private function photos(): void
    {
        $palette = ['doctor2' => [[124, 58, 237], [244, 114, 182]], 'nurse' => [[14, 116, 144], [134, 239, 172]], 'pharmacist' => [[194, 65, 12], [253, 230, 138]], 'admin' => [[30, 64, 175], [147, 197, 253]]];
        foreach ($palette as $who => [$from, $to]) {
            if (! isset($this->ids[$who]) || ! function_exists('imagecreatetruecolor')) {
                continue;
            }
            $path = $this->files->newPath('avatars', 'png');
            $this->files->disk()->put($path, $this->image(256, 256, $from, $to, portrait: true));
            Person::query()->whereKey($this->ids[$who])->update(['photo_path' => $path]);
        }
    }

    /**
     * A PNG with a diagonal gradient (and a head-and-shoulders silhouette for portraits).
     *
     * @param  int<1, max>  $w
     * @param  int<1, max>  $h
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function image(int $w, int $h, array $from, array $to, bool $portrait = false): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            // 1×1 transparent PNG when GD is missing.
            return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        }
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $mix = fn (int $i): int => max(0, min(255, (int) ($from[$i] + ($to[$i] - $from[$i]) * $t)));
            $color = imagecolorallocate($img, $mix(0), $mix(1), $mix(2));
            imageline($img, 0, $y, $w, $y, (int) $color);
        }
        $white = (int) imagecolorallocatealpha($img, 255, 255, 255, 30);
        if ($portrait) {
            imagefilledellipse($img, (int) ($w / 2), (int) ($h * 0.4), (int) ($w * 0.38), (int) ($w * 0.38), $white);
            imagefilledellipse($img, (int) ($w / 2), (int) ($h * 1.02), (int) ($w * 0.8), (int) ($h * 0.7), $white);
        } else {
            imagefilledellipse($img, (int) ($w * 0.3), (int) ($h * 0.45), (int) ($w * 0.35), (int) ($h * 0.5), $white);
            imagefilledrectangle($img, (int) ($w * 0.55), (int) ($h * 0.3), (int) ($w * 0.85), (int) ($h * 0.36), $white);
            imagefilledrectangle($img, (int) ($w * 0.55), (int) ($h * 0.45), (int) ($w * 0.78), (int) ($h * 0.51), $white);
            imagefilledrectangle($img, (int) ($w * 0.55), (int) ($h * 0.6), (int) ($w * 0.82), (int) ($h * 0.66), $white);
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /** A two-second, two-note chime as WAV (a stand-in voice note that plays). */
    private function tone(): string
    {
        $rate = 16000;
        $samples = '';
        for ($i = 0; $i < $rate * 2; $i++) {
            $freq = $i < $rate ? 523.25 : 659.25;
            $fade = 1 - (($i % $rate) / $rate);
            $samples .= pack('v', (int) (sin(2 * M_PI * $freq * $i / $rate) * 9000 * $fade) & 0xFFFF);
        }

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    }

    /** A one-page PDF. */
    private function pdf(): string
    {
        $text = 'Physio plan - bed 6: knee mobilisation 2x daily, assisted walking 10 min, review Thursday.';
        $stream = "BT /F1 12 Tf 50 780 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
