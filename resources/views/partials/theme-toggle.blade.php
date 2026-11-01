{{--
    Light / dark switch. Drop this inside the header action row; it reuses the
    .notif-btn look so it sits flush next to the notification bell. The no-flash
    <head> script (partials/theme-mode.blade.php) has already set data-theme by
    the time this runs - here we just flip it on click and remember the choice.
--}}
<button type="button" class="notif-btn theme-toggle-btn" id="themeToggle" aria-label="Switch colour theme">
    <i class='bx bx-moon'></i>
</button>

<script>
    (function () {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;

        var root = document.documentElement,
            icon = btn.querySelector('i'),
            mq = window.matchMedia('(prefers-color-scheme: dark)');

        function current() {
            return root.getAttribute('data-theme') || (mq.matches ? 'dark' : 'light');
        }

        function apply(mode) {
            root.setAttribute('data-theme', mode);
            root.setAttribute('data-bs-theme', mode);
            if (icon) icon.className = mode === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
            btn.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }

        // Sync the icon to whatever the head script decided.
        apply(current());

        // One quick fade of the whole screen from the old colours to the new
        // (View Transitions; 0.2s set in partials/app-shell/styles). Fading
        // each element on its own looked like flicker ("masakit sa mata",
        // 2026-10-25), and the diagonal wipe before it was too much.
        // Instant where the browser can't do it, or with reduced motion.
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        function switchTo(next) {
            try { localStorage.setItem('themeMode', next); } catch (e) {}
            if (document.startViewTransition && !reduceMotion.matches) {
                document.startViewTransition(function () { apply(next); });
            } else {
                apply(next);
            }
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            switchTo(current() === 'dark' ? 'light' : 'dark');
        });

        // Keep following the OS until the user makes an explicit choice.
        mq.addEventListener('change', function (e) {
            var stored = null;
            try { stored = localStorage.getItem('themeMode'); } catch (e) {}
            if (stored !== 'dark' && stored !== 'light') apply(e.matches ? 'dark' : 'light');
        });
    })();
</script>
