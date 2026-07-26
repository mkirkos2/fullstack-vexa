<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                'max:50000',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('The message content cannot be empty or whitespace only.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages()
    {
        return [
            'content.required' => 'The message content is required.',
            'content.string' => 'The message content must be a string.',
            'content.max' => 'The message content must not exceed 50,000 characters.',
        ];
    }
}
