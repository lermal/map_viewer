<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Render Pages</title>
    <meta name="description" content="Welcome to the Shipyard - Choose a category to view renders">
    <meta property="og:title" content="{{ config('app.name', 'Laravel') }} - Render Pages">
    <meta property="og:description" content="Welcome to the Shipyard - Choose a category to view renders">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('render.index') }}">
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
                <h1 class="render-index-title">Welcome to the Shipyard</h1>
                <p class="render-index-subtitle">Choose a category to view renders</p>
            </div>
            @if ($pages->isEmpty())
                <div class="render-index-empty">
                    <p>No render pages available.</p>
                </div>
            @else
                <div class="render-index-grid">
                    @foreach ($pages as $page)
                        <a href="{{ route('render.page', $page->slug) }}" class="render-index-card">
                            <div class="render-index-card__content">
                                <h2 class="render-index-card__title">{{ $page->name }}</h2>
                                @if ($page->description)
                                    <p class="render-index-card__description">{{ $page->description }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                    <a href="{{ route('user-renders.index') }}" class="render-index-card">
                        <div class="render-index-card__content">
                            <h2 class="render-index-card__title">User Renders</h2>
                            <p class="render-index-card__description">Renders submitted by users</p>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </main>
</body>

</html>
