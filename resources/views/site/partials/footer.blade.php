{{-- The public site's footer, WhatsApp button and the few lines of script every public page uses. --}}
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub" width="185" height="44">
                <p class="footer-about">School management software for Ugandan primary and secondary schools.</p>
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="{{ route('filament.app.site.page', 'features') }}">Features</a></li>
                    <li><a href="{{ route('filament.app.site.page', 'pricing') }}">Pricing</a></li>
                    <li><a href="{{ route('filament.app.site.page', 'about') }}">About SchoolHub</a></li>
                    <li><a href="{{ route('filament.app.site.page', 'help') }}">Help</a></li>
                </ul>
            </div>
            <div>
                <h4>Get started</h4>
                <ul>
                    <li><a href="{{ $registerUrl }}">Register your school</a></li>
                    <li><a href="{{ $contact['windows_download'] }}" rel="nofollow">Download for Windows</a></li>
                    <li><a href="{{ $loginUrl }}">Sign in</a></li>
                    <li><a href="{{ filament()->getRequestPasswordResetUrl() }}">Reset password</a></li>
                    <li><a href="{{ route('filament.app.legal.terms') }}">Terms &amp; conditions</a></li>
                </ul>
            </div>
            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="{{ route('filament.app.site.page', 'contact') }}">Contact us</a></li>
                    <li><a href="{{ $demoUrl }}">Book a demo</a></li>
                    <li><a href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp {{ $contact['phone'] }}</a></li>
                    <li><a href="{{ $telUrl }}">Call {{ $contact['phone'] }}</a></li>
                    <li><a href="{{ $mailUrl }}">{{ $contact['email'] }}</a></li>
                    <li>{{ $contact['location'] }}</li>
                    <li>{{ $contact['hours'] }}</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} SchoolHub. All rights reserved.</span>
            <span>Developed by {{ $contact['company'] }} · Made in Uganda</span>
        </div>
    </div>
</footer>

<a href="{{ $waUrl }}" class="wa-float" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg><span>Chat with us</span></a>

<script>
    (function () {
        var header = document.getElementById('header');
        var menuBtn = document.getElementById('menu-btn');

        // Border and shadow on the sticky header once the page scrolls.
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        // Mobile menu.
        menuBtn.addEventListener('click', function () {
            var open = header.classList.toggle('is-open');
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.querySelectorAll('#mobile-nav a').forEach(function (a) {
            a.addEventListener('click', function () {
                header.classList.remove('is-open');
                menuBtn.setAttribute('aria-expanded', 'false');
            });
        });

        // Fade sections in as they scroll into view. Content stays visible if
        // the browser can't observe (or the visitor prefers less motion).
        var revealables = document.querySelectorAll('[data-reveal]');
        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px' });

            revealables.forEach(function (el) {
                // Stagger siblings in the same grid slightly.
                el.style.animationDelay = (Array.prototype.indexOf.call(el.parentNode.children, el) % 3) * 80 + 'ms';
                observer.observe(el);
            });
            document.documentElement.classList.add('reveal-ready');
        }

        // Per term / per year pricing.
        var root = document.getElementById('pricing-root');
        root && document.querySelectorAll('[data-set-cycle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-cycle', btn.dataset.setCycle);
                document.querySelectorAll('[data-set-cycle]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
            });
        });
    })();
</script>
