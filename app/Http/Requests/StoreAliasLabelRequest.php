<?php

namespace App\Http\Requests;

use App\Models\Label;
use App\Rules\UserLabelId;
use Illuminate\Foundation\Http\FormRequest;

class StoreAliasLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alias_id' => [
                'required',
                'uuid',
            ],
            'label_ids' => [
                'bail',
                'array',
                'max:'.Label::LABELS_PER_ALIAS_LIMIT,
                new UserLabelId,
            ],
            'label_ids.*' => 'required|uuid|distinct',
        ];
    }
}
