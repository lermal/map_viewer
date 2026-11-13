<?php

namespace App\Services;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class VisitTrackerService
{
    /**
     * Crawlers, link previews and scripted clients are not counted as visits.
     */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|pinterest|vkshare|'
        .'whatsapp|telegram|discord|skype|validator|lighthouse|headless|python|curl|wget|httpclient|okhttp|go-http|java\//i';

    /**
     * Record a visit after the response has been sent. A failure here never affects the page.
     */
    public function track(Request $request, ?Model $visitable = null): void
    {
        if ($this->isBot($request->userAgent())) {
            return;
        }

        $data = [
            'visitable_type' => $visitable ? $visitable::class : null,
            'visitable_id' => $visitable?->getKey(),
            'route_name' => $request->route()?->getName() ?? 'unknown',
            'path' => Visit::pathFromUrl($request->getPathInfo()),
            'ip_hash' => Visit::hashIp((string) $request->ip()),
        ];

        app()->terminating(function () use ($data) {
            try {
                Visit::create($data);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }
}
