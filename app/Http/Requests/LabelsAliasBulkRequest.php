<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBulkIds;
use App\Models\Label;
use App\Rules\UserLabelId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LabelsAliasBulkRequest extends FormRequest
{
    use NormalizesBulkIds;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ids' => $this->normalizedBulkIds($this->ids),
            'label_ids' => $this->normalizedBulkIds($this->label_ids ?? []),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|max:25|min:1',
            'ids.*' => 'required|uuid|distinct',
            'label_ids' => [
                'array',
                'max:'.Label::LABELS_PER_ALIAS_LIMIT,
                new UserLabelId,
            ],
            'label_ids.*' => 'required|uuid|distinct',
        ];
    }
}
