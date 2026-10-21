{{-- "Share" on an announcement - its card and pop-up on the student page, and
     its own page. Opens the phone's share sheet where there is one, otherwise
     copies the link. A share link is an <a data-share data-title="..."> whose
     href is the announcement's own address, so without JavaScript it simply
     opens that page. --}}
<script>
(function () {
    'use strict';

    const timers = new WeakMap();

    function flash(link, text) {
        const label = link.querySelector('[data-share-label]');
        if (! label) return;
        if (! label.dataset.text) label.dataset.text = label.textContent;
        label.textContent = text;
        clearTimeout(timers.get(link));
        timers.set(link, setTimeout(() => { label.textContent = label.dataset.text; }, 2000));
    }

    document.addEventListener('click', e => {
        const link = e.target.closest('[data-share]');
        // Ctrl/Cmd/Shift-click still opens the announcement's page in a new tab or window.
        if (! link || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        const url = link.href;

        if (navigator.share) {
            navigator.share({ title: link.dataset.title, url }).catch(() => {}); // closed without sharing
        } else if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => flash(link, 'Link copied'), () => window.prompt('Copy this link:', url));
        } else {
            window.prompt('Copy this link:', url);
        }
    });
})();
</script>
