<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Render - {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="Upload your render">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @include('header')
    <main style="margin-top: 90px; position: relative; z-index: 1; min-height: calc(100vh - 90px); overflow-y: auto;">
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
        <div class="render-index-container" style="max-width: 800px;">
            <div class="render-index-header">
                <h1 class="render-index-title">Upload Render</h1>
                <p class="render-index-subtitle">Your render will be reviewed by a moderator before publication</p>
            </div>

            @if ($errors->any())
                <div style="background: #ef4444; color: white; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('user-renders.store') }}" method="POST" enctype="multipart/form-data" class="user-render-form">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">
                        Name <span class="required">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required class="form-input">
                </div>

                <div class="form-group">
                    <label for="image" class="form-label">
                        Image <span class="required">*</span>
                    </label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/webp" required class="form-input">
                    <p class="form-help">
                        Maximum size: 10 MB. Formats: JPEG, PNG, WebP
                    </p>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        Description
                    </label>
                    <textarea id="description" name="description" rows="4" class="form-textarea">{{ old('description') }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }}>
                        <span>Make render public (visible to everyone after moderation)</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary form-submit">
                    <i class="ri-upload-cloud-2-line"></i> Upload Render
                </button>
            </form>

            <div style="margin-top: 32px; text-align: center;">
                <a href="{{ route('user-renders.index') }}" class="btn-primary" style="display: inline-block;">
                    <i class="ri-arrow-left-line"></i> Back to List
                </a>
            </div>
        </div>
    </main>
</body>

</html>

