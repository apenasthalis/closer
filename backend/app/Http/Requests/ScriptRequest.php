<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
            'category' => ['nullable', 'string', 'max:60'],
            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*.title' => ['required_with:sections', 'string', 'max:120'],
            'sections.*.content' => ['required_with:sections', 'string', 'max:10000'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'playbook_id' => ['nullable', 'integer', 'exists:playbooks,id'],
        ];
    }
}
