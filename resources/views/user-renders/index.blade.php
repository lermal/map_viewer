<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Renders - {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="View user-submitted renders">
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
                <a href="{{ route('user-renders.create') }}" class="btn-primary" style="margin-top: 20px;">
                    <i class="ri-upload-cloud-2-line"></i> Upload Your Render
                </a>
            </div>
            @if ($renders->isEmpty())
                <div class="render-index-empty">
                    <p>No approved user renders yet.</p>
                </div>
            @else
                <div class="render-index-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                    @foreach ($renders as $render)
                        <a href="{{ route('user-renders.show', $render) }}" class="render-index-card">
                            <div class="render-index-card__image"
                                style="width: 100%; height: 200px; overflow: hidden; border-radius: 8px 8px 0 0;">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($render->image) }}"
                                    alt="{{ $render->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="render-index-card__content">
                                <h2 class="render-index-card__title">{{ $render->name }}</h2>
                                @if ($render->description)
                                    <p class="render-index-card__description">
                                        {{ \Illuminate\Support\Str::limit($render->description, 100) }}</p>
                                @endif
                                @if ($render->user)
                                    <p style="font-size: 0.875rem; color: #888; margin-top: 8px;">
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
