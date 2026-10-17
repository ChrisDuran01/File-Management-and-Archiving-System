{{--
    Shared toast messages for every officer page - included by the Admin and
    SuperAdmin layouts (Admin/home.blade.php, SuperAdmin/homeSuperAdmin.blade.php),
    so a page never draws its own success/error banner.

    Shows whatever the last request left behind: session('success'),
    session('error') and validation errors. A success message leaves on its
    own; an error stays until dismissed, since it usually needs acting on.

    Page scripts can raise one too:
        window.showToast('Saved.')
        window.showToast('Something went wrong.', 'error')
--}}
@php
    $toasts = [];
    if (session('success')) {
        $toasts[] = ['type' => 'success', 'lines' => [session('success')]];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'lines' => [session('error')]];
    }
    if (isset($errors) && $errors->any()) {
        // Every validation message in one toast, not one toast each.
        $toasts[] = ['type' => 'error', 'lines' => $errors->all()];
    }
@endphp

<style>
    /* Top centre, just under the header: clear of the upload panel and the
       notification bell (top right) and of the help bubble (bottom right). */
    .app-toasts {
        position: fixed;
        top: calc(var(--header-height, 3rem) + 1rem + 12px);
        left: 50%;
        transform: translateX(-50%);
        z-index: 2100;
        width: min(440px, calc(100vw - 32px));
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }
    .app-toast {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 12px 12px 14px;
        background: var(--card, #fff);
        color: var(--text-1, #111827);
        border: 1px solid var(--border, #e8eaf0);
        border-left: 4px solid var(--primary, #058028);
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .16);
        font-size: .875rem;
        line-height: 1.45;
        pointer-events: auto;
        animation: app-toast-in .22s ease-out;
    }
    .app-toast.error { border-left-color: var(--danger, #dc2626); }
    .app-toast-icon { flex-shrink: 0; margin-top: 1px; font-size: 1.05rem; color: var(--primary, #058028); }
    .app-toast.error .app-toast-icon { color: var(--danger, #dc2626); }
    .app-toast-text { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .app-toast-text ul { margin: 0; padding-left: 1.1em; }
    .app-toast-close {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        margin: -5px -4px -5px 0;
        border: 0;
        border-radius: 8px;
        background: none;
        color: var(--text-3, #9ca3af);
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }
    .app-toast-close:hover { background: var(--nav-hover-bg, #f4f6f9); color: var(--text-1, #111827); }
    .app-toast.leaving { opacity: 0; transform: translateY(-6px); transition: opacity .2s, transform .2s; }
    @keyframes app-toast-in { from { opacity: 0; transform: translateY(-10px); } }
    @media (prefers-reduced-motion: reduce) {
        .app-toast { animation: none; }
        .app-toast.leaving { transition: none; }
    }
</style>

<div class="app-toasts" id="appToasts"></div>

<script>
    (function () {
        const stack = document.getElementById('appToasts');

        // lines: one message, or several (validation errors) shown as a list.
        window.showToast = function (lines, type) {
            lines = [].concat(lines);
            const isError = type === 'error';

            const toast = document.createElement('div');
            toast.className = 'app-toast' + (isError ? ' error' : '');
            // Added after the page loads, so screen readers announce it:
            // an error interrupts (alert), a success waits its turn (status).
            toast.setAttribute('role', isError ? 'alert' : 'status');

            const icon = document.createElement('i');
            icon.className = 'app-toast-icon bi ' + (isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill');
            icon.setAttribute('aria-hidden', 'true');

            // textContent, never innerHTML: messages carry folder and file names.
            const text = document.createElement('div');
            text.className = 'app-toast-text';
            if (lines.length > 1) {
                const list = document.createElement('ul');
                lines.forEach(line => {
                    const item = document.createElement('li');
                    item.textContent = line;
                    list.append(item);
                });
                text.append(list);
            } else {
                text.textContent = lines[0];
            }

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'app-toast-close';
            close.setAttribute('aria-label', 'Dismiss message');
            close.innerHTML = '&times;';

            let timer = null;
            const dismiss = () => {
                clearTimeout(timer);
                toast.classList.add('leaving');
                setTimeout(() => toast.remove(), 220);
            };
            close.addEventListener('click', dismiss);

            // Success leaves on its own (longer text gets longer), but not
            // while it's being read: the timer waits for the pointer or
            // keyboard focus to move away. An error stays until dismissed.
            if (! isError) {
                const readTime = Math.max(5000, lines.join(' ').length * 70);
                const arm = () => { clearTimeout(timer); timer = setTimeout(dismiss, readTime); };
                toast.addEventListener('mouseenter', () => clearTimeout(timer));
                toast.addEventListener('mouseleave', arm);
                toast.addEventListener('focusin', () => clearTimeout(timer));
                toast.addEventListener('focusout', arm);
                arm();
            }

            toast.append(icon, text, close);
            stack.append(toast);
        };

        const pending = @json($toasts);
        document.addEventListener('DOMContentLoaded', () => pending.forEach(t => window.showToast(t.lines, t.type)));
    })();
</script>
