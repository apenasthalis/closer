<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payment_terms' => ['nullable', 'string', 'max:5000'],
            'clauses' => ['nullable', 'array', 'max:50'],
            'clauses.*' => ['string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
