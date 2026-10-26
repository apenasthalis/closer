<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['nota', 'ligacao', 'whatsapp', 'email', 'reuniao', 'proposta', 'contrato', 'pagamento', 'outro'])],
            'summary' => ['required', 'string', 'min:2', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
