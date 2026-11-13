{{-- Shared <head> content of public pages: metadata for search engines and link previews, icons, styles. --}}
@props([
    'title',
    'description',
    'canonical' => null,
    'image' => null,
    'largeImage' => false,
    'type' => 'website',
    'robots' => null,
    'jsonLd' => [],
])
@php
    $image ??= asset('images/Logo.png');
    $url = $canonical ?? url()->current();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
@if ($robots)
    <meta name="robots" content="{{ $robots }}">
@endif
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif
<meta property="og:site_name" content="{{ config('app.name', 'Shipyard') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:image" content="{{ $image }}">
<meta name="twitter:card" content="{{ $largeImage ? 'summary_large_image' : 'summary' }}">
<meta name="theme-color" content="#121212">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
@foreach ($jsonLd as $data)
    <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
{{ $slot }}
