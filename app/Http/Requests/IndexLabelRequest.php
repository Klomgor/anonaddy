<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => [
                'nullable',
                'array',
            ],
            'filter.search' => [
                'nullable',
                'string',
                'max:50',
                'min:1',
            ],
        ];
    }
}
