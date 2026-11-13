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
        <h1 class="visually-hidden">{{ $page->name }} of Frontier Station 14</h1>
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
                                    @foreach ($filters as $filterName => $filterValues)
                                            @if (isset($item[$filterName]))
                                                data-{{ $filterName }}="{{ is_array($item[$filterName]) ? implode(', ', $item[$filterName]) : $item[$filterName] }}"
                                            @endif @endforeach>
                                    <a class="shuttle-link" href="{{ route('render.show', [$page->slug, $item['id']]) }}">
                                        <div class="shuttle-name">{{ $item['name'] }}</div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </main>
</body>

</html>
