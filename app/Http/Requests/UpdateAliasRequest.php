<?php

namespace App\Http\Requests;

use App\Models\Label;
use App\Rules\UserLabelId;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAliasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'description' => 'nullable|max:200',
            'from_name' => 'nullable|string|max:50',
            'label_ids' => [
                'bail',
                'nullable',
                'array',
                'max:'.Label::LABELS_PER_ALIAS_LIMIT,
                new UserLabelId,
            ],
            'label_ids.*' => 'required|uuid|distinct',
        ];
    }
}
