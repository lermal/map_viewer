<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $userRender->name }} - {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="{{ $userRender->description ?? $userRender->name }}">
    <meta property="og:title" content="{{ $userRender->name }}">
    <meta property="og:description" content="{{ $userRender->description ?? $userRender->name }}">
    <meta property="og:image" content="{{ \Illuminate\Support\Facades\Storage::url($userRender->image) }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('user-renders.show', $userRender) }}">
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
        <div class="render-index-container" style="max-width: 1200px;">
            <div style="margin-bottom: 32px;">
                <a href="{{ route('user-renders.index') }}" class="btn-primary" style="display: inline-block;">
                    <i class="ri-arrow-left-line"></i> Back to List
                </a>
            </div>

            <div style="background: rgba(0, 0, 0, 0.5); padding: 32px; border-radius: 12px;">
                <h1 style="font-size: 2.5rem; margin-bottom: 16px; color: #fff;">{{ $userRender->name }}</h1>

                @if ($userRender->user)
                    <p style="font-size: 1rem; color: #888; margin-bottom: 24px;">
                        Author: {{ $userRender->user->name }}
                    </p>
                @endif

                <div style="margin-bottom: 24px; text-align: center;">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($userRender->image) }}"
                        alt="{{ $userRender->name }}"
                        style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);">
                </div>

                @if ($userRender->description)
                    <div style="margin-top: 32px;">
                        <h2 style="font-size: 1.5rem; margin-bottom: 16px; color: #fff;">Description</h2>
                        <p style="color: #ccc; line-height: 1.6; white-space: pre-wrap;">{{ $userRender->description }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </main>
</body>

</html>
