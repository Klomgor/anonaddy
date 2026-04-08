<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlocklistBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation (normalise each value to lowercase and trim).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('values') && is_array($this->values)) {
            $this->merge([
                'values' => array_values(array_filter(array_map(function ($value) {
                    return is_string($value) ? strtolower(trim($value)) : $value;
                }, $this->values))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['email', 'domain']),
            ],
            'values' => ['required', 'array', 'min:1', 'max:50'],
            'values.*' => [
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = $this->input('type');
                    if ($type === 'email') {
                        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fail('The value must be a valid email address when type is email.');
                        }
                    }
                    if ($type === 'domain') {
                        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value)) {
                            $fail('The value must be a valid domain (e.g. example.com) when type is domain.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors (UK English).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select whether you are blocking emails or domains.',
            'type.in' => 'The type must be either email or domain.',
            'values.required' => 'Please enter at least one entry.',
            'values.max' => 'You may add at most 50 entries at once.',
        ];
    }
}
