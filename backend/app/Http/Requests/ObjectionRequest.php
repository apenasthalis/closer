<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'label' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['nullable', 'string', 'max:60'],
            'stage' => ['nullable', 'string', 'max:40'],
            'context' => ['nullable', 'string', 'max:5000'],
            'times_raised' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'times_resolved' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'what_worked' => ['nullable', 'string', 'max:5000'],
            'best_rebuttal' => ['nullable', 'string', 'max:5000'],
            'result' => ['nullable', 'string', 'max:40'],
        ];
    }
}
