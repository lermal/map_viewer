{{-- Error pages in the site style. Only static texts: exception messages are never shown to visitors. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-page-head :title="$title.' - '.config('app.name', 'Shipyard')" :description="$message" robots="noindex, nofollow" />
</head>

<body>
    @include('header')
    <main style="margin-top: 90px; position: relative; z-index: 1; min-height: calc(100vh - 90px);">
        @include('background')
        <div class="render-index-container" style="max-width: 800px;">
            <div class="render-index-header">
                <h1 class="render-index-title">{{ $code }} · {{ $title }}</h1>
                <p class="render-index-subtitle">{{ $message }}</p>
            </div>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('render.index') }}" class="btn-primary">
                    <x-ri name="rocket-2-fill" /> Back to Home
                </a>
                @if ($retry ?? false)
                    <a href="{{ url()->current() }}" class="btn-primary">
                        <x-ri name="refresh-line" /> Try Again
                    </a>
                @endif
            </div>
        </div>
    </main>
</body>

</html>
