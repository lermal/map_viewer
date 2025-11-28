<header>
    <nav class="navigation">
        <nav class="navbar">
            <div class="navbar-brand">
                <img src="https://shipyard.frontierstation14.com/images/Logo.webp" alt="Frontier Station" width="140"
                    height="62.06">
            </div>
            <div class="navbar-items">
                <a class="btn-primary" href="{{ route('render.index') }}"><i class="ri-home-line"></i> Home</a>
                @isset($renderPages)
                    @foreach ($renderPages as $renderPage)
                        <a class="btn-primary" href="{{ route('render.page', $renderPage->slug) }}"><i
                                class="ri-image-line"></i> {{ $renderPage->name }}</a>
                    @endforeach
                @endisset
                <a class="btn-primary" href="https://discord.gg/frontier"><i class="ri-discord-fill"></i> Discord</a>
                <a class="btn-primary" href="https://frontierstation14.com/"><i class="ri-booklet-fill"></i> Wiki</a>
                <a class="btn-primary" href="https://github.com/new-frontiers-14/frontier-station-14"><i
                        class="ri-github-fill"></i> Github</a>
            </div>
        </nav>
    </nav>
</header>
