<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(6)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_color' => ['nullable', 'string', 'max:20'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }
}
