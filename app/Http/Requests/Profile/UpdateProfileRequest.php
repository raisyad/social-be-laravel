<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['nullable','string','max:100'],
            'bio'       => ['nullable','string','max:160'],
            'birthdate' => ['nullable','date','before:today'],
            'gender'    => ['nullable','in:male,female'],

            // file opsional; FE bisa kirim multipart/form-data
            'avatar'    => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
            'cover'     => ['nullable','image','mimes:jpg,jpeg,png','max:4096'],
        ];
    }
}
