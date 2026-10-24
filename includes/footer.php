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
</script>

<script src="<?= BASE_URL ?>/assets/js/ethiopian_calendar.js"></script>
<?php if (file_exists(__DIR__ . '/../sw.js')): ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js', { scope: '<?= BASE_URL ?>/' })
            .catch(function (err) { console.warn('SW registration failed:', err); });
    });
}
</script>
<?php endif; ?>
</body>
</html>
