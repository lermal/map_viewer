<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $userRender->name }} - {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="{{ $userRender->description ?? $userRender->name }}">
    <meta name="robots" content="noindex, nofollow">
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
    <main class="render-show-main">
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
        <div class="render-viewer">
            <div class="render-viewer__loader" id="render-loader">
                <div class="render-viewer__loader-spinner"></div>
                <div class="render-viewer__loader-text">Loading render...</div>
            </div>
            <div class="render-viewer__image-container">
                <img class="render-viewer__image"
                    src="{{ \Illuminate\Support\Facades\Storage::url($userRender->image) }}"
                    alt="{{ $userRender->name }}">
            </div>
        </div>
        <div class="render-info-panel">
            <div class="render-info-panel__header">
                <h2 class="render-info-panel__title">{{ $userRender->name }}</h2>
            </div>
            <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                <a href="{{ route('user-renders.index') }}" class="btn-primary"
                    style="display: inline-block; font-size: 14px; padding: 8px 12px;">
                    <i class="ri-arrow-left-line"></i> Back to List
                </a>
                <button onclick="copyRenderLink(event)" class="btn-primary"
                    style="font-size: 14px; padding: 8px 12px; cursor: pointer;">
                    <i class="ri-link"></i> Copy Link
                </button>
            </div>
            <div class="render-info-panel__content">
                @if ($userRender->user)
                    <div class="render-info-panel__field">
                        <div class="render-info-panel__field-name">Author:</div>
                        <div class="render-info-panel__field-value">{{ $userRender->user->name }}</div>
                    </div>
                @endif
                @if ($userRender->status === 'pending')
                    <div class="render-info-panel__field">
                        <div class="render-info-panel__field-name">Status:</div>
                        <div class="render-info-panel__field-value">
                            <span style="color: #fbbf24;">Pending Moderation</span>
                        </div>
                    </div>
                @elseif ($userRender->status === 'rejected')
                    <div class="render-info-panel__field">
                        <div class="render-info-panel__field-name">Status:</div>
                        <div class="render-info-panel__field-value">
                            <span style="color: #ef4444;">Rejected</span>
                        </div>
                    </div>
                @elseif ($userRender->status === 'approved')
                    <div class="render-info-panel__field">
                        <div class="render-info-panel__field-name">Status:</div>
                        <div class="render-info-panel__field-value">
                            <span style="color: #10b981;">Approved</span>
                        </div>
                    </div>
                @endif
            </div>
            @if ($userRender->description)
                <div class="render-info-panel__description">
                    {{ $userRender->description }}
                </div>
            @endif
        </div>
        @if (session('success') || $userRender->status === 'pending' || $userRender->status === 'rejected')
            <div class="user-render-status-notification">
                @if (session('success'))
                    <div class="success-message">
                        <i class="ri-checkbox-circle-fill"></i>
                        <div>
                            <strong>Success!</strong>
                            <p>{{ session('success') }}</p>
                            @if ($userRender->status === 'pending')
                                <p class="success-note">Your render is now available via direct link and is awaiting
                                    moderation.</p>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($userRender->status === 'pending')
                    <div class="status-message status-pending">
                        <i class="ri-time-line"></i>
                        <div>
                            <strong>Awaiting Moderation</strong>
                            <p>Your render is pending review. It's only accessible via direct link.</p>
                        </div>
                    </div>
                @elseif ($userRender->status === 'rejected')
                    <div class="status-message status-rejected">
                        <i class="ri-close-circle-line"></i>
                        <div>
                            <strong>Rejected</strong>
                            <p>Your render has been rejected by a moderator.</p>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </main>
    <script>
        const renderImage = document.querySelector('.render-viewer__image');
        const renderLoader = document.getElementById('render-loader');

        function hideLoader() {
            setTimeout(() => {
                if (renderLoader) {
                    renderLoader.style.opacity = '0';
                    renderLoader.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        renderLoader.style.display = 'none';
                    }, 300);
                }
            }, 500);
        }

        if (renderImage) {
            if (renderImage.complete) {
                hideLoader();
            } else {
                renderImage.addEventListener('load', hideLoader);
                renderImage.addEventListener('error', hideLoader);
            }
        }

        function copyRenderLink(event) {
            event.preventDefault();
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="ri-check-line"></i> Copied!';
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            }).catch(function() {
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="ri-check-line"></i> Copied!';
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            });
        }
    </script>
</body>

</html>
