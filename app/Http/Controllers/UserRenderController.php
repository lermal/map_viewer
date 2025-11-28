<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRenderRequest;
use App\Models\UserRender;
use App\Services\VisitTrackerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        UserRender::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'image' => $imagePath,
            'description' => $request->description,
            'status' => 'pending',
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('user-renders.create')
            ->with('success', 'Render uploaded successfully and is awaiting moderation.');
    }

    public function index(Request $request)
    {
        $this->visitTracker->track($request);

        $renders = UserRender::where('status', 'approved')
            ->where('is_public', true)
            ->latest()
            ->paginate(24);

        return view('user-renders.index', compact('renders'));
    }

    public function show(Request $request, UserRender $userRender)
    {
        if ($userRender->status !== 'approved' || ! $userRender->is_public) {
            abort(404);
        }

        $this->visitTracker->track($request);

        return view('user-renders.show', compact('userRender'));
    }
}
