<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VisitDailyStat extends Model
{
    protected $fillable = [
        'date',
        'views',
        'unique_visitors',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'unique_visitors' => 'integer',
        ];
    }

    /**
     * Stored as a plain Y-m-d string on every database, so lookups by day match exactly.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : Carbon::parse($value)->startOfDay(),
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }
}
