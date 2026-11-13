<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?? $page->name }} - {{ config('app.name', 'Laravel') }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <meta property="og:title" content="{{ $page->meta_title ?? $page->name }} - {{ config('app.name', 'Laravel') }}">
    @if ($page->meta_description)
        <meta property="og:description" content="{{ $page->meta_description }}">
    @elseif ($page->description)
        <meta property="og:description" content="{{ $page->description }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('render.page', $page->slug) }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @include('header')
    <main style="margin-top: 90px; position: relative; z-index: 1;">
        @include('background')
        <div class="dropdown" style="position: relative; z-index: 10;">
            <div class="dropbtn__container">
                <button class="dropbtn btn-primary render-toggle"><x-ri name="corner-left-down-line" /> Select
                    render</button>
                <button class="dropbtn btn-primary filter-toggle"><x-ri name="filter-3-line" /> Filter
                    renders</button>
            </div>
            <div class="filter-dropdown">
                @if (!empty($filters))
                    @foreach ($filters as $filterName => $filterValues)
                        @if (!empty($filterValues))
                            <div class="dropdown-category">
                                <strong class="group-name">{{ ucfirst($filterName) }}</strong>
                                <hr>
                                <ul class="shuttle-list">
                                    @foreach ($filterValues as $value)
                                        <li class="shuttle-item">
                                            <label class="checkbox-item" style="opacity: 1;">
                                                <input type="checkbox" value="{{ $value }}"
                                                    class="render-filter" data-filter-type="{{ $filterName }}">
                                                <span class="checkbox-text">{{ $value }}</span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="dropdown-content">
                <div class="render-search-container">
                    <input type="text" id="render-search" class="render-search-input"
                        placeholder="Search renders by name...">
                    <x-ri name="search-line" class="render-search-icon" />
                </div>
                <div id="no-results-message" style="display: none; text-align: center; padding: 20px; color: #f2f4f1;">
                    No renders found matching the selected filters
                </div>
                @foreach ($categories as $category)
                    <div class="dropdown-category">
                        <strong class="group-name">{{ $category['name'] }}</strong>
                        <hr>
                        <ul class="shuttle-list">
                            @foreach ($category['items'] as $item)
                                <li class="shuttle-item render-item" data-id="{{ $item['id'] }}"
                                    data-name="{{ $item['name'] }}"
                                    @if (isset($item['category'])) data-category="{{ $item['category'] }}" @endif
                                    @if (isset($item['description'])) data-description="{{ $item['description'] }}" @endif
                                    @foreach ($filters as $filterName => $filterValues)
                                            @if (isset($item[$filterName]))
                                                data-{{ $filterName }}="{{ is_array($item[$filterName]) ? implode(', ', $item[$filterName]) : $item[$filterName] }}"
                                            @endif @endforeach>
                                    <div class="shuttle-name">{{ $item['name'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </main>
    <script>
        window.pageSlug = '{{ $page->slug }}';
    </script>
</body>

</html>
