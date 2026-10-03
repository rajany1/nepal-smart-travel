{{-- Landing site header — identical to web/home.blade.php --}}
<header class="site-header">
    <div class="container header-container">
        <a href="{{ route('web.home') }}" class="brand-logo" aria-label="Oripori Home">
            <img src="{{ asset('images/oripori_logo.svg') }}" alt="Oripori Logo">
        </a>

        <nav>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="{{ route('web.home') }}#explore" class="nav-link">Explore</a></li>
                <li><a href="{{ route('web.home') }}#safety" class="nav-link">Safety &amp; SOS</a></li>
                <li><a href="{{ route('web.home') }}#community" class="nav-link">Community Insights</a></li>
                <li><a href="{{ route('web.home') }}#rewards" class="nav-link">Oripori Coins</a></li>
                <li><a href="{{ route('web.home') }}#values" class="nav-link">Core Values</a></li>
            </ul>
        </nav>

        <a href="{{ route('partner.login') }}" class="btn-cta-placeholder">Partner Login</a>

        <button class="nav-mobile-toggle" id="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="nav-menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</header>
