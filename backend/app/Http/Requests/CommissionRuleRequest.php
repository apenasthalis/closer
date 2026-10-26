<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'scope' => ['required', Rule::in(['global', 'user', 'team', 'niche'])],
            'user_id' => ['nullable', 'required_if:scope,user', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'required_if:scope,team', 'integer', 'exists:teams,id'],
            'niche_id' => ['nullable', 'required_if:scope,niche', 'uuid', 'exists:niches,id'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_without:fixed_amount'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'basis' => ['required', Rule::in(['contracted', 'received'])],
            'active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
