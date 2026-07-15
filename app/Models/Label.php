<?php

namespace App\Models;

use App\Traits\HasUuid;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;
    use HasUuid;

    public const LABEL_LIMIT = 100;

    public const LABELS_PER_ALIAS_LIMIT = 10;

    public const COLOURS = [
        '#06b6d4',
        '#22c55e',
        '#eab308',
        '#f97316',
        '#ef4444',
        '#8b5cf6',
        '#64748b',
        '#ec4899',
        '#14b8a6',
        '#3b82f6',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'colour',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aliases()
    {
        return $this->belongsToMany(Alias::class, 'alias_label');
    }
}
