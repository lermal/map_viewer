<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $item['name'] }} - {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body>
    @include('header')
    <main class="render-show-main">
        <div class="dropdown" style="position: fixed; z-index: 200;">
            <div class="dropbtn__container">
                <button class="dropbtn btn-primary render-toggle"><i class="ri-corner-left-down-line"></i> Select
                    render</button>
                <button class="dropbtn btn-primary filter-toggle"><i class="ri-filter-3-line"></i> Filter
                    renders</button>
            </div>
            <div class="filter-dropdown">
                <div class="filter-container">
                    @foreach ($filters as $filterName => $filterValues)
                        <div class="filter-section">
                            <h3 class="filter-title">{{ ucfirst($filterName) }}</h3>
                            <div class="checkbox-list">
                                @foreach ($filterValues as $value)
                                    <label class="checkbox-item" style="opacity: 1;">
                                        <input type="checkbox" value="{{ $value }}" class="render-filter"
                                            data-filter-type="{{ $filterName }}">
                                        <span class="checkbox-text">{{ $value }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="dropdown-content">
                <div id="no-results-message" style="display: none; text-align: center; padding: 20px; color: #f2f4f1;">
                    No renders found matching the selected filters
                </div>
                @foreach ($categories as $category)
                    <div class="dropdown-category">
                        <strong class="group-name">{{ $category['name'] }}</strong>
                        <hr>
                        <ul class="shuttle-list">
                            @foreach ($category['items'] as $categoryItem)
                                <li class="shuttle-item render-item" data-id="{{ $categoryItem['id'] }}"
                                    data-name="{{ $categoryItem['name'] }}"
                                    @if (isset($categoryItem['category'])) data-category="{{ $categoryItem['category'] }}" @endif
                                    @if (isset($categoryItem['description'])) data-description="{{ $categoryItem['description'] }}" @endif
                                    @foreach ($filters as $filterName => $filterValues)
                                            @if (isset($categoryItem[$filterName]))
                                                data-{{ $filterName }}="{{ is_array($categoryItem[$filterName]) ? implode(', ', $categoryItem[$filterName]) : $categoryItem[$filterName] }}"
                                            @endif @endforeach>
                                    <div class="shuttle-name">{{ $categoryItem['name'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
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
            <div class="render-viewer__image-container">
                <img class="render-viewer__image"
                    src="{{ asset('storage/' . $data['renders_path'] . $item['image']) }}" alt="{{ $item['name'] }}">
            </div>
        </div>
        <div class="render-info-panel">
            <div class="render-info-panel__header">
                <h2 class="render-info-panel__title">{{ $item['name'] }}</h2>
                @if (isset($item['price']))
                    <div class="render-info-panel__price">
                        {{ number_format($item['price'], 0, '.', ' ') }}
                    </div>
                @endif
            </div>
            <div class="render-info-panel__content">
                @if (!empty($additionalFields))
                    @foreach ($additionalFields as $fieldName => $fieldValue)
                        <div class="render-info-panel__field">
                            <div class="render-info-panel__field-name">
                                {{ ucfirst(str_replace('_', ' ', $fieldName)) }}:
                            </div>
                            <div class="render-info-panel__field-value">
                                @if (is_array($fieldValue))
                                    <div class="render-info-panel__tags">
                                        @foreach ($fieldValue as $tag)
                                            <span class="render-info-panel__tags-item">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    {{ $fieldValue }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            @if (isset($item['description']))
                <div class="render-info-panel__description">
                    {{ $item['description'] }}
                </div>
            @endif
        </div>
    </main>
    <script>
        const renderData = @json($item);
        const rendersPath = '{{ $data['renders_path'] }}';
        window.renderData = @json($items);
        window.filters = @json($filters);
        window.pageSlug = '{{ $page->slug }}';
    </script>
</body>

</html>
