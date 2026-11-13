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
        'path',
        'ip_hash',
    ];

    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Keyed, irreversible hash of an IP address: unique visitors can be counted without storing the address.
     */
    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * Path of a URL without host and query string, limited to the column size.
     */
    public static function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return mb_substr(is_string($path) && $path !== '' ? $path : '/', 0, 255);
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

        return $baseQuery->distinct('ip_hash')->count('ip_hash');
    }
}
