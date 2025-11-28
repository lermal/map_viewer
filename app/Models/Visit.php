<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Visit extends Model
{
    protected $fillable = [
        'visitable_type',
        'visitable_id',
        'route_name',
        'url',
        'ip_address',
        'user_agent',
    ];

    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public static function getUniqueVisitorsCount($query = null): int
    {
        $baseQuery = $query ?? static::query();

        return $baseQuery->distinct('ip_address')->count('ip_address');
    }
}
