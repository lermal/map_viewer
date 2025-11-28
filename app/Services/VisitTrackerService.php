<?php

namespace App\Services;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class VisitTrackerService
{
    public function track(Request $request, ?Model $visitable = null): Visit
    {
        return Visit::create([
            'visitable_type' => $visitable ? $visitable::class : null,
            'visitable_id' => $visitable?->id,
            'route_name' => $request->route()?->getName() ?? 'unknown',
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
