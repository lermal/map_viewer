<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-page-head :title="$meta['title']" :description="$meta['description']" :canonical="$meta['canonical']"
        :image="$meta['image'] ?? null" :large-image="$meta['largeImage'] ?? false" :json-ld="$meta['jsonLd']" />
</head>

<body>
    @include('header')
    <main style="margin-top: 90px; position: relative; z-index: 1;">
        @include('background')
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
