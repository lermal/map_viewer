<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-page-head :title="$meta['title']" :description="$meta['description']" :canonical="$meta['canonical']"
        :image="$meta['image'] ?? null" :large-image="$meta['largeImage'] ?? false" :json-ld="$meta['jsonLd']" />
</head>

<body>
    @include('header')
    <main class="render-show-main">
        <div class="dropdown" style="position: fixed; z-index: 200;">
            <div class="dropbtn__container">
                <button class="dropbtn btn-primary render-toggle"><x-ri name="corner-left-down-line" /> Select
                    render</button>
                <button class="dropbtn btn-primary filter-toggle"><x-ri name="filter-3-line" /> Filter
                    renders</button>
                @if (!empty($item['map']))
                    <button class="dropbtn btn-primary layers-toggle"><x-ri name="stack-line" /> Layers</button>
                @endif
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
            @if (!empty($item['map']))
                <div class="filter-dropdown layers-dropdown">
                    <div class="dropdown-category">
                        <strong class="group-name">Subfloor</strong>
                        <hr>
                        <ul class="shuttle-list">
                            <li class="shuttle-item">
                                <label class="checkbox-item"><input type="checkbox" data-layer="subfloor"><span
                                        class="checkbox-text">Show subfloor</span></label>
                            </li>
                            <li class="shuttle-item">
                                <label class="checkbox-item"><input type="checkbox" data-layer="subfloorOnTop"><span
                                        class="checkbox-text">Above objects</span></label>
                            </li>
                        </ul>
                        <ul class="shuttle-list layers-dropdown__kinds">
                            @foreach (['hv' => 'HV cables', 'mv' => 'MV cables', 'lv' => 'LV cables', 'pipe' => 'Pipes', 'disposal' => 'Disposals', 'subfloor' => 'Other'] as $kind => $label)
                                <li class="shuttle-item">
                                    <label class="checkbox-item"><input type="checkbox" data-kind="{{ $kind }}" checked><span
                                            class="checkbox-text">{{ $label }} <span class="layers-dropdown__count"
                                                data-count="{{ $kind }}"></span></span></label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="dropdown-category">
                        <strong class="group-name">Power</strong>
                        <hr>
                        <ul class="shuttle-list">
                            <li class="shuttle-item">
                                <label class="checkbox-item"><input type="checkbox" data-layer="powered" checked><span
                                        class="checkbox-text">Powered</span></label>
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
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
                            @foreach ($category['items'] as $categoryItem)
                                <li class="shuttle-item render-item" data-id="{{ $categoryItem['id'] }}"
                                    data-name="{{ $categoryItem['name'] }}"
                                    @if (isset($categoryItem['category'])) data-category="{{ $categoryItem['category'] }}" @endif
                                    @foreach ($filters as $filterName => $filterValues)
                                            @if (isset($categoryItem[$filterName]))
                                                data-{{ $filterName }}="{{ is_array($categoryItem[$filterName]) ? implode(', ', $categoryItem[$filterName]) : $categoryItem[$filterName] }}"
                                            @endif @endforeach>
                                    <a class="shuttle-link" href="{{ route('render.show', [$page->slug, $categoryItem['id']]) }}"
                                        @if ($categoryItem['id'] === $item['id']) aria-current="page" @endif>
                                        <div class="shuttle-name">{{ $categoryItem['name'] }}</div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
        @include('background')
        @if (!empty($item['map']))
            <div class="render-viewer" data-map-url="{{ asset('storage/' . $item['map']) }}"
                @if ($imageUrl) data-fallback-src="{{ $imageUrl }}" @endif
                data-alt="{{ $item['name'] }}">
                <div class="render-viewer__loader" id="render-loader">
                    <div class="render-viewer__loader-spinner"></div>
                    <div class="render-viewer__loader-text">Loading render...</div>
                </div>
                <canvas class="render-viewer__canvas" aria-label="{{ $item['name'] }}" role="img"></canvas>
                <div class="render-viewer__image-container"></div>
                @include('render.placeholder', ['hidden' => true])
            </div>
            <div class="render-hover" hidden></div>
        @elseif ($imageUrl)
            <div class="render-viewer">
                <div class="render-viewer__loader" id="render-loader">
                    <div class="render-viewer__loader-spinner"></div>
                    <div class="render-viewer__loader-text">Loading render...</div>
                </div>
                <div class="render-viewer__image-container">
                    <img class="render-viewer__image" src="{{ $imageUrl }}" alt="{{ $item['name'] }}">
                </div>
            </div>
        @else
            <div class="render-viewer">
                @include('render.placeholder', ['hidden' => false])
            </div>
        @endif
        <div class="render-info-panel">
            <div class="render-info-panel__header">
                <h1 class="render-info-panel__title">{{ $item['name'] }}</h1>
                @if (isset($item['price']) && $item['price'] > 0)
                    <div class="render-info-panel__price">
                        {{ number_format($item['price'], 0, '.', ' ') }}
                    </div>
                @endif
            </div>
            <div class="render-info-panel__content">
                @if (!empty($additionalFields))
                    @foreach ($additionalFields as $fieldName => $fieldValue)
                        @php
                            $isEmpty = false;
                            if (is_array($fieldValue)) {
                                $isEmpty = empty($fieldValue);
                            } else {
                                $isEmpty = empty($fieldValue) && $fieldValue !== '0' && $fieldValue !== 0;
                            }
                        @endphp
                        @if (!$isEmpty)
                            <div class="render-info-panel__field">
                                <div class="render-info-panel__field-name">
                                    {{ ucfirst(str_replace('_', ' ', $fieldName)) }}:
                                </div>
                                <div class="render-info-panel__field-value">
                                    @if (is_array($fieldValue))
                                        <div class="render-info-panel__tags">
                                            @foreach ($fieldValue as $tag)
                                                @if (!empty($tag))
                                                    <span
                                                        class="render-info-panel__tags-item">{{ $tag }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        {{ $fieldValue }}
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
                @if (!empty($item['map']))
                    <div class="render-info-panel__field render-info-panel__attribution" hidden>
                        <div class="render-info-panel__field-name">Sprites:</div>
                        <div class="render-info-panel__field-value">
                            <a href="https://github.com/new-frontiers-14/frontier-station-14" target="_blank"
                                rel="noopener noreferrer">Space Station 14 / Frontier Station 14</a>
                            <div class="render-info-panel__tags render-info-panel__licenses"></div>
                        </div>
                    </div>
                @endif
            </div>
            @if (isset($item['description']) && !empty(trim($item['description'])))
                <div class="render-info-panel__description">
                    {{ $item['description'] }}
                </div>
            @endif
        </div>
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

        window.hideRenderLoader = hideLoader;

        if (renderImage) {
            if (renderImage.complete) {
                hideLoader();
            } else {
                renderImage.addEventListener('load', hideLoader);
                renderImage.addEventListener('error', hideLoader);
            }
        }
    </script>
</body>

</html>
