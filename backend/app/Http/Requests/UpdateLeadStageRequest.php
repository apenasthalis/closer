<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(Lead::STAGES)],
            'reason' => ['nullable', 'required_if:stage,loss,mal_qualificado', 'string', 'max:2000'],
            'temperature' => ['nullable', Rule::in(['cold', 'warm', 'hot'])],
            'position' => ['nullable', 'integer', 'min:0'],
            'ticket_fechado' => ['nullable', 'numeric', 'min:0'],
            'valor_negociado' => ['nullable', 'numeric', 'min:0'],
            'next_action_at' => ['nullable', 'date'],
            'next_steps' => ['nullable', 'array', 'max:20'],
            'next_steps.*' => ['string', 'max:300'],
        ];
    }
}
