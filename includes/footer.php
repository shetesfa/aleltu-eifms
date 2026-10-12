    </div>
</div>
<script>
(function () {
    var toggle = document.getElementById('navToggle');
    var overlay = document.getElementById('navOverlay');
    var sidebar = document.getElementById('sidebar');
    function closeNav() {
        document.body.classList.remove('sidebar-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
    function openNav() {
        document.body.classList.add('sidebar-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.contains('sidebar-open') ? closeNav() : openNav();
        });
    }
    if (overlay) overlay.addEventListener('click', closeNav);
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 900px)').matches) closeNav();
            });
        });
    }
    window.addEventListener('resize', function () {
        if (!window.matchMedia('(max-width: 900px)').matches) closeNav();
    });
})();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/aleltu-eifms/sw.js', { scope: '/aleltu-eifms/' })
            .catch(function (err) { console.warn('SW registration failed:', err); });
    });
}
</script>
</body>
</html>
