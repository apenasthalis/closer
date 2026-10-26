<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'uuid', 'exists:leads,id'],
            'closer_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'no_show', 'cancelled'])],
            'started_at' => ['nullable', 'date'],
            'duration_sec' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'transcript' => ['nullable', 'array'],
            'transcript.text' => ['nullable', 'string', 'max:200000'],
            'transcript.lines' => ['nullable', 'array', 'max:2000'],
            'transcript.lines.*.speaker' => ['nullable', 'string', 'max:80'],
            'transcript.lines.*.text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
