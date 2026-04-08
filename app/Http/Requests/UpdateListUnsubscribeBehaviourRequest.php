<?php

namespace App\Http\Requests;

use App\Enums\ListUnsubscribeBehaviour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListUnsubscribeBehaviourRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'list_unsubscribe_behaviour' => [
                'required',
                'integer',
                Rule::in(array_column(ListUnsubscribeBehaviour::cases(), 'value')),
            ],
        ];
    }
}
