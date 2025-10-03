<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:users,username'],
            'password' => ['required', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'device_name' => ['nullable','string','max:100'],
        ];
    }
}
