<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Renders - {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="View user-submitted renders">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @include('header')
    <main style="margin-top: 90px; position: relative; z-index: 1;">
        <div class="render-background">
            <div class="render-background__layer render-background__layer--back"
                style="background-image: url('{{ asset('images/parallax--back.webp') }}');"></div>
            <div class="render-background__layer render-background__layer--front"
                style="background-image: url('{{ asset('images/parallax--front.webp') }}');"></div>
            <canvas class="render-background__layer render-background__layer--stars1" id="stars-canvas-1"></canvas>
            <canvas class="render-background__layer render-background__layer--stars2" id="stars-canvas-2"></canvas>
            <div class="render-background__layer render-background__layer--meteors1"
                style="background-image: url('{{ asset('images/parallax--meteors.webp') }}');"></div>
            <div class="render-background__layer render-background__layer--meteors2"
                style="background-image: url('{{ asset('images/parallax--meteors.webp') }}');"></div>
        </div>
        <div class="render-index-container">
            <div class="render-index-header">
                <h1 class="render-index-title">User Renders</h1>
                <p class="render-index-subtitle">Renders submitted by users</p>
            </div>
            <div
                style="text-align: center; margin-bottom: 40px; display: flex; flex-direction: column; align-items: center; gap: 20px;">
                <a href="{{ route('user-renders.create') }}" class="btn-primary">
                    <i class="ri-upload-cloud-2-line"></i> Upload Your Render
                </a>
                <form action="{{ route('user-renders.index') }}" method="GET" class="user-renders-search-form">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name..."
                        class="user-renders-search-input">
                    <button type="submit" class="btn-primary">
                        <i class="ri-search-line"></i> Search
                    </button>
                    @if (request('search'))
                        <a href="{{ route('user-renders.index') }}" class="btn-primary">
                            <i class="ri-close-line"></i> Clear
                        </a>
                    @endif
                </form>
            </div>

            @if (isset($myRenders) && $myRenders->isNotEmpty())
                <div style="margin-bottom: 50px;">
                    <h2 style="color: #f2f4f1; font-size: 28px; margin-bottom: 24px; text-align: center;">My Renders
                    </h2>
                    <div class="render-index-grid"
                        style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        @foreach ($myRenders as $render)
                            <a href="{{ route('user-renders.show', $render->slug) }}"
                                class="render-index-card render-index-card--with-image">
                                <div class="render-index-card__image">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($render->image) }}"
                                        alt="{{ $render->name }}">
                                </div>
                                <div class="render-index-card__content render-index-card__content--no-top-clip">
                                    <h2 class="render-index-card__title">{{ $render->name }}</h2>
                                    @if ($render->description)
                                        <p class="render-index-card__description">
                                            {{ \Illuminate\Support\Str::limit($render->description, 100) }}</p>
                                    @endif
                                    <p class="render-index-card__status" style="font-size: 0.875rem; margin-top: 8px;">
                                        @if ($render->status === 'pending')
                                            <span style="color: #fbbf24;">⏳ Pending</span>
                                        @elseif ($render->status === 'approved')
                                            <span style="color: #10b981;">✓ Approved</span>
                                        @elseif ($render->status === 'rejected')
                                            <span style="color: #ef4444;">✗ Rejected</span>
                                        @endif
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <h2 style="color: #f2f4f1; font-size: 28px; margin-bottom: 24px; text-align: center; margin-top: 40px;">All
                Renders</h2>
            @if ($renders->isEmpty())
                <div class="render-index-empty">
                    <p>No approved user renders yet.</p>
                </div>
            @else
                <div class="render-index-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                    @foreach ($renders as $render)
                        <a href="{{ route('user-renders.show', $render) }}"
                            class="render-index-card render-index-card--with-image">
                            <div class="render-index-card__image">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($render->image) }}"
                                    alt="{{ $render->name }}">
                            </div>
                            <div class="render-index-card__content render-index-card__content--no-top-clip">
                                <h2 class="render-index-card__title">{{ $render->name }}</h2>
                                @if ($render->description)
                                    <p class="render-index-card__description">
                                        {{ \Illuminate\Support\Str::limit($render->description, 100) }}</p>
                                @endif
                                @if ($render->user)
                                    <p class="render-index-card__author">
                                        Author: {{ $render->user->name }}
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div style="margin-top: 40px; display: flex; justify-content: center;">
                    {{ $renders->links() }}
                </div>
            @endif
        </div>
    </main>
</body>

</html>
