<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'username'    => ['required', 'string', 'min:3', 'max:30', 'regex:/^(?=.{3,30}$)[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'device_name' => ['nullable','string','max:100'],
        ];
    }
}
