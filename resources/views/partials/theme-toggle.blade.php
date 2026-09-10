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

        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        // Diagonal wipe: dark starts top-left and grows toward bottom-right;
        // light reverses, starting bottom-right and closing on top-left.
        function switchTo(next) {
            var persist = function () {
                try { localStorage.setItem('themeMode', next); } catch (e) {}
                apply(next);
            };

            if (!document.startViewTransition || reduceMotion.matches) {
                persist();
                return;
            }

            var fromTopLeft = next === 'dark';
            var x = fromTopLeft ? 0 : window.innerWidth;
            var y = fromTopLeft ? 0 : window.innerHeight;
            var endRadius = Math.hypot(window.innerWidth, window.innerHeight);

            var transition = document.startViewTransition(function () {
                root.classList.add('theme-flip-lock');
                persist();
            });
            transition.ready.then(function () {
                // Snapshots are captured - safe to unfreeze transitions and
                // start the reveal.
                root.classList.remove('theme-flip-lock');
                root.animate(
                    {
                        clipPath: [
                            'circle(0px at ' + x + 'px ' + y + 'px)',
                            'circle(' + endRadius + 'px at ' + x + 'px ' + y + 'px)'
                        ]
                    },
                    {
                        duration: 600,
                        easing: 'ease-in-out',
                        pseudoElement: '::view-transition-new(root)'
                    }
                );
            }).catch(function () {
                root.classList.remove('theme-flip-lock');
            });
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
