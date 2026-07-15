<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Arr;

trait NormalizesBulkIds
{
    /**
     * @return array<int, mixed>
     */
    protected function normalizedBulkIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return Arr::whereNotNull($ids);
    }
}
