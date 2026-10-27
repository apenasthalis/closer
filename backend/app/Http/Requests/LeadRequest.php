<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'origin' => ['nullable', 'string', 'max:60'],
            'stage' => ['sometimes', Rule::in(Lead::STAGES)],
            'temperature' => ['nullable', Rule::in(['cold', 'warm', 'hot'])],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'position' => ['nullable', 'integer', 'min:0'],
            'closer_id' => ['nullable', 'integer', 'exists:users,id'],
            'pre_sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'ticket_estimado' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'valor_negociado' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'ticket_fechado' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'receita_prevista' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'first_contact_at' => ['nullable', 'date'],
            'last_interaction_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
            'next_steps' => ['nullable', 'array', 'max:20'],
            'next_steps.*' => ['string', 'max:300'],
            'loss_reason' => ['nullable', 'string', 'max:2000'],
            'disqualify_reason' => ['nullable', 'string', 'max:2000'],
            'saldo_devedor' => ['nullable', 'numeric', 'min:0'],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'parcelas' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
