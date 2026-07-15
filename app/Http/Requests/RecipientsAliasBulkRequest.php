<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBulkIds;
use App\Rules\VerifiedRecipientId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecipientsAliasBulkRequest extends FormRequest
{
    use NormalizesBulkIds;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ids' => $this->normalizedBulkIds($this->ids),
            'recipient_ids' => $this->normalizedBulkIds($this->recipient_ids ?? []),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|max:25|min:1',
            'ids.*' => 'required|uuid|distinct',
            'recipient_ids' => [
                'array',
                'max:10',
                new VerifiedRecipientId,
            ],
            'recipient_ids.*' => 'required|uuid|distinct',
        ];
    }
}
