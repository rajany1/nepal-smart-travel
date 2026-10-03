{{-- Mobile navigation toggle — identical to web/home.blade.php --}}
<script>
    (function () {
        var toggle = document.getElementById('nav-toggle');
        var menu = document.getElementById('nav-menu');
        if (toggle && menu) {
            toggle.addEventListener('click', function () {
                var open = menu.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                var icon = toggle.querySelector('i');
                if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
            });
            menu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    menu.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    var icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-bars';
                });
            });
        }
    })();
</script>
