<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRenderRequest;
use App\Models\UserRender;
use App\Services\UserRenderImageStore;
use App\Services\VisitTrackerService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class UserRenderController extends Controller
{
    private const IMAGE_TYPES = ['image/webp', 'image/png', 'image/jpeg'];

    public function __construct(
        protected VisitTrackerService $visitTracker
    ) {}

    public function create()
    {
        return view('user-renders.create');
    }

    public function store(StoreUserRenderRequest $request, UserRenderImageStore $images)
    {
        $key = 'user-render-uploads:'.$request->ip();
        $perHour = (int) config('user-renders.uploads_per_hour');

        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return back()->withInput()->withErrors([
                'image' => "You can upload up to {$perHour} renders per hour. Please try again in {$minutes} min.",
            ]);
        }

        $quota = (int) config('user-renders.pending_quota_mb') * 1024 * 1024;
        if (UserRender::pendingBytes() + $request->file('image')->getSize() > $quota) {
            return back()->withInput()->withErrors([
                'image' => 'Uploads are temporarily closed while moderators review the queue. Please try again later.',
            ]);
        }

        $stored = $images->store($request->file('image'));

        $userRender = UserRender::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'image' => $stored['path'],
            'size' => $stored['size'],
            'description' => $request->description,
            'status' => 'pending',
            'is_public' => $request->boolean('is_public'),
        ]);

        RateLimiter::hit($key, 3600);

        $myRenders = $request->session()->get('my_renders', []);
        $myRenders[] = [
            'slug' => $userRender->slug,
            'name' => $request->name,
            'created_at' => now()->toDateTimeString(),
        ];
        $request->session()->put('my_renders', $myRenders);

        return redirect()->route('user-renders.show', $userRender);
    }

    public function index(Request $request)
    {
        $this->visitTracker->track($request);

        $query = UserRender::where('status', 'approved')
            ->where('is_public', true);

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $renders = $query->latest()->paginate(24)->withQueryString();

        $myRendersSlugs = $request->session()->get('my_renders', []);
        $myRenders = collect();

        if (!empty($myRendersSlugs)) {
            $slugs = array_column($myRendersSlugs, 'slug');
            $myRenders = UserRender::whereIn('slug', $slugs)
                ->latest()
                ->get();
        }

        return view('user-renders.index', compact('renders', 'myRenders'));
    }

    public function show(Request $request, string $slug)
    {
        $userRender = UserRender::where('slug', $slug)->firstOrFail();

        $this->visitTracker->track($request);

        return view('user-renders.show', compact('userRender'));
    }

    /**
     * Image behind the secret link: pending renders for anyone with the link,
     * any render for administrators. Approved files are served statically.
     */
    public function image(Request $request, string $slug)
    {
        $userRender = UserRender::where('slug', $slug)->firstOrFail();
        $isAdmin = (bool) $request->user()?->canAccessPanel(Filament::getPanel('admin'));

        abort_if($userRender->status === 'rejected' && ! $isAdmin, 404);

        $disk = $userRender->imageDisk();
        abort_if($disk === null, 404);

        if ($disk === UserRender::PUBLIC_DISK && ! $isAdmin) {
            return redirect()->away(Storage::disk($disk)->url($userRender->image));
        }

        $mime = Storage::disk($disk)->mimeType($userRender->image);
        abort_unless(in_array($mime, self::IMAGE_TYPES, true), 404);

        return Storage::disk($disk)->response($userRender->image, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
