<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Requests;

use App\Enums\Communication\ConversationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A direct chat with one colleague (`user_id`), or a named group (`title` + `member_ids`). */
final class StartConversationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            'user_id' => ['required_if:type,direct', 'nullable', 'uuid'],
            'title' => ['required_if:type,group', 'nullable', 'string', 'min:2', 'max:100'],
            'member_ids' => ['required_if:type,group', 'array', 'max:100'],
            'member_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
