{{-- Landing site footer — identical to web/home.blade.php --}}
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="{{ asset('images/oripori_logo_wordmark.png') }}" alt="Oripori White Logo">
                </div>
                <p>
                    A Nepal-first platform connecting people with what&rsquo;s around them&mdash;local information, community insights, safety, places, and businesses.
                </p>
            </div>

            <div>
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="{{ route('web.places') }}">Places &amp; Trails</a></li>
                    <li><a href="{{ route('web.category', 'hotels') }}">Hotels &amp; Stays</a></li>
                    <li><a href="{{ route('web.category', 'restaurants') }}">Restaurants</a></li>
                    <li><a href="{{ route('web.routes') }}">Curated Routes</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-heading">Company</h4>
                <ul class="footer-links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="{{ route('partner.register') }}">Partner With Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-heading">Support</h4>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Safety</a></li>
                    <li><a href="{{ route('web.legal.show', 'terms') }}">Terms of Service</a></li>
                    <li><a href="{{ route('web.legal.show', 'privacy') }}">Privacy Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; Oripori. All rights reserved.</div>
        </div>
    </div>
</footer>
