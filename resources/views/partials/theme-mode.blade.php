{{--
    Dark-mode bootstrap. Include as the FIRST thing inside <head> on every
    layout that wants light/dark support, BEFORE any stylesheet, so the
    correct theme is painted with no flash of the wrong colours.

    Resolution order:
      1. localStorage 'themeMode'  ('dark' | 'light')  - the user's explicit choice
      2. OS  prefers-color-scheme                       - the default until they choose

    Both data-theme (our own token overrides, see Admin/partials/theme.blade.php)
    and data-bs-theme (Bootstrap 5.3's native dark variables) are set together.
    The visible switch lives in partials/theme-toggle.blade.php.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('themeMode');
            var mode = (stored === 'dark' || stored === 'light')
                ? stored
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var el = document.documentElement;
            el.setAttribute('data-theme', mode);
            el.setAttribute('data-bs-theme', mode);
        } catch (e) {}
    })();
</script>
