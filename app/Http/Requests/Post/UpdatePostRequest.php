<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
            'content'             => ['sometimes','required','string','max:2000'],
            'media'               => ['sometimes','array','max:10'],
            'media.*'             => ['file','mimes:jpg,jpeg,png,mp4','max:10240'],
            'remove_media_ids'    => ['sometimes','array'],
            'remove_media_ids.*'  => ['integer','exists:post_media,id'],
        ];
    }
}
