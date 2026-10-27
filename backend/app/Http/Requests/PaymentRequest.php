<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'uuid', 'exists:leads,id'],
            'contract_id' => ['nullable', 'uuid', 'exists:contracts,id'],
            'proposal_id' => ['nullable', 'uuid', 'exists:proposals,id'],
            'kind' => ['required', Rule::in(['installment', 'entry', 'recurring', 'other'])],
            'installment_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'due_at' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'method' => ['nullable', 'string', Rule::exists('payment_sources', 'slug')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
        ];
    }
}
