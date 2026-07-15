<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserLabelId implements ValidationRule
{
    protected ?array $userLabelIds;

    public function __construct(?array $userLabelIds = null)
    {
        $this->userLabelIds = $userLabelIds;
    }

    public function validate(string $attribute, mixed $ids, Closure $fail): void
    {
        if (is_null($this->userLabelIds)) {
            $this->userLabelIds = user()
                ->labels()
                ->pluck('id')
                ->toArray();
        }

        if (! is_array($ids)) {
            $fail('Invalid Label');

            return;
        }

        foreach ($ids as $id) {
            if (! in_array($id, $this->userLabelIds)) {
                $fail('Invalid Label');
            }
        }
    }
}
