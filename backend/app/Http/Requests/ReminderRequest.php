<?php

namespace App\Http\Requests;

use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'uuid', 'exists:leads,id'],
            'type' => ['required', Rule::in(Reminder::TYPES)],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['required', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'sent_to_lead' => ['nullable', 'boolean'],
        ];
    }
}
