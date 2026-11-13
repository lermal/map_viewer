<header>
    <nav class="navigation">
        <nav class="navbar">
            <a class="navbar-brand" href="{{ route('render.index') }}" aria-label="Shipyard home">
                <img src="{{ asset('images/Logo.webp') }}" alt="Frontier Station" width="140" height="62">
            </a>
            <button class="navbar-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="navbar-items">
                <a class="btn-primary" href="{{ route('render.index') }}"><x-ri name="rocket-2-fill" /> Home</a>
                <a class="btn-primary" href="https://discord.gg/frontier"><x-ri name="discord-fill" /> Discord</a>
                <a class="btn-primary" href="https://frontierstation14.com/"><x-ri name="booklet-fill" /> Wiki</a>
                <a class="btn-primary" href="https://github.com/new-frontiers-14/frontier-station-14"><x-ri name="github-fill" /> Github</a>
            </div>
        </nav>
    </nav>
</header>
<a class="site-credit" href="https://forged.by/projects/shipyard-mapviewer" target="_blank" rel="noopener noreferrer">Developed by Forged</a>
