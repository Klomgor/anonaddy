<?php

namespace App\Http\Requests;

use App\Helpers\CustomEmailValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'max:254',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = $this->input('email');

            if (! is_string($email) || ! CustomEmailValidator::isValid($email)) {
                $validator->errors()->add('email', 'Valid email required');
            }
        });
    }
}
