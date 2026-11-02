<?php

declare(strict_types=1);

namespace App\Models\Communication;

use App\Enums\Communication\BubbleColor;
use App\Enums\Communication\BubbleStyle;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's chat preferences: how bubbles look, and whether new messages pop up and count in the bell badge.
 * Read through ChatService::settings(), which returns the defaults when none are saved.
 *
 * @property string $user_id
 * @property BubbleStyle $bubble_style
 * @property BubbleColor $my_color
 * @property BubbleColor $others_color
 * @property bool $notify_messages
 * @property bool $count_in_badge
 */
class ChatSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['user_id', 'bubble_style', 'my_color', 'others_color', 'notify_messages', 'count_in_badge'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'bubble_style' => 'rounded',
        'my_color' => 'blue',
        'others_color' => 'green',
        'notify_messages' => true,
        'count_in_badge' => true,
    ];

    protected function casts(): array
    {
        return [
            'bubble_style' => BubbleStyle::class,
            'my_color' => BubbleColor::class,
            'others_color' => BubbleColor::class,
            'notify_messages' => 'boolean',
            'count_in_badge' => 'boolean',
        ];
    }
}
