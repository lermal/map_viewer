<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRenderRequest;
use App\Models\UserRender;
use App\Services\VisitTrackerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserRenderController extends Controller
{
    public function __construct(
        protected VisitTrackerService $visitTracker
    ) {}

    public function create()
    {
        return view('user-renders.create');
    }

    public function store(StoreUserRenderRequest $request)
    {
        $imagePath = $request->file('image')->store('user-renders', 'public');

        do {
            $slug = Str::random(32);
        } while (UserRender::where('slug', $slug)->exists());

        $userRender = UserRender::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'slug' => $slug,
            'image' => $imagePath,
            'description' => $request->description,
            'status' => 'pending',
            'is_public' => $request->boolean('is_public'),
        ]);

        $myRenders = $request->session()->get('my_renders', []);
        $myRenders[] = [
            'slug' => $slug,
            'name' => $request->name,
            'created_at' => now()->toDateTimeString(),
        ];
        $request->session()->put('my_renders', $myRenders);

        return redirect()->route('user-renders.show', $userRender)
            ->with('success', 'Render uploaded successfully! Your render is now available via direct link and is awaiting moderation.');
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
}
