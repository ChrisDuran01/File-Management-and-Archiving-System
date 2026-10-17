{{--
    Shared confirmation dialog for every officer page - included by the Admin
    and SuperAdmin layouts (Admin/home.blade.php, SuperAdmin/homeSuperAdmin.blade.php).
    Used instead of the browser's own confirm() box.

    On a form - it only submits once the officer confirms:
        <form ... data-confirm-title="Delete this announcement?"
                  data-confirm="It will be removed from the student page. This cannot be undone."
                  data-confirm-ok="Delete">
    Only data-confirm-title or data-confirm is required. The confirm button
    is red by default; data-confirm-tone="warning" makes it amber, for
    changes that can be reversed (archiving, switching something off).

    From a script:
        confirmAction({ title, message, ok, tone }).then(yes => { ... })

    Names and other record text go in the attributes through {{ }}, never
    into an inline script string, where an apostrophe would break the prompt.
--}}
<style>
    .app-confirm {
        width: min(420px, calc(100vw - 32px));
        padding: 0;
        border: 1px solid var(--border, #e8eaf0);
        border-radius: 16px;
        background: var(--card, #fff);
        color: var(--text-1, #111827);
        box-shadow: 0 24px 64px rgba(15, 23, 42, .28);
    }
    .app-confirm::backdrop { background: rgba(15, 23, 42, .5); }
    .app-confirm[open] { animation: app-confirm-in .16s ease-out; }
    @keyframes app-confirm-in { from { opacity: 0; transform: translateY(8px) scale(.98); } }
    .app-confirm-box { padding: 24px; }
    .app-confirm-icon {
        width: 44px;
        height: 44px;
        margin-bottom: 14px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: var(--danger-dim, #fef2f2);
        color: var(--danger, #dc2626);
        font-size: 1.2rem;
    }
    .app-confirm.warning .app-confirm-icon { background: var(--warning-dim, #fffbeb); color: var(--warning, #b45309); }
    .app-confirm-title { margin: 0 0 6px; font-size: 1.05rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .app-confirm-text {
        margin: 0;
        color: var(--text-2, #6b7280);
        font-size: .9rem;
        line-height: 1.5;
        white-space: pre-line;
        overflow-wrap: anywhere;
    }
    .app-confirm-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 22px; }
    .app-confirm-actions button {
        min-height: 40px;
        padding: 0 16px;
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
    }
    .app-confirm-cancel { border: 1px solid var(--border, #e8eaf0); background: var(--card, #fff); color: var(--text-1, #111827); }
    .app-confirm-cancel:hover { background: var(--nav-hover-bg, #f4f6f9); }
    /* Fixed colours: both keep white text readable in light and dark mode. */
    .app-confirm-ok { border: 1px solid transparent; background: #dc2626; color: #fff; }
    .app-confirm-ok:hover { background: #b91c1c; }
    .app-confirm.warning .app-confirm-ok { background: #b45309; }
    .app-confirm.warning .app-confirm-ok:hover { background: #92400e; }
    .app-confirm-actions button:focus-visible { outline: 3px solid var(--primary, #058028); outline-offset: 2px; }
    @media (max-width: 420px) {
        .app-confirm-actions button { flex: 1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .app-confirm[open] { animation: none; }
    }
</style>

<dialog class="app-confirm" id="appConfirm" aria-labelledby="appConfirmTitle" aria-describedby="appConfirmText">
    <div class="app-confirm-box">
        <div class="app-confirm-icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
        <h2 class="app-confirm-title" id="appConfirmTitle"></h2>
        <p class="app-confirm-text" id="appConfirmText"></p>
        <div class="app-confirm-actions">
            {{-- Cancel comes first and takes focus, so a stray Enter never deletes. --}}
            <button type="button" class="app-confirm-cancel" id="appConfirmCancel" autofocus>Cancel</button>
            <button type="button" class="app-confirm-ok" id="appConfirmOk"></button>
        </div>
    </div>
</dialog>

<script>
    (function () {
        const dialog = document.getElementById('appConfirm');
        const title = document.getElementById('appConfirmTitle');
        const text = document.getElementById('appConfirmText');
        const ok = document.getElementById('appConfirmOk');
        let settle = null;

        // Resolves true when confirmed; false on Cancel, Esc or a click outside.
        window.confirmAction = function (options) {
            options = options || {};
            // One question at a time: asking again cancels the one before.
            if (settle) { settle(false); settle = null; }
            title.textContent = options.title || 'Are you sure?';
            // textContent, never innerHTML: messages carry folder and file names.
            text.textContent = options.message || '';
            text.hidden = ! options.message;
            ok.textContent = options.ok || 'Confirm';
            dialog.classList.toggle('warning', options.tone === 'warning');
            dialog.returnValue = '';
            if (! dialog.open) dialog.showModal();
            return new Promise(resolve => { settle = resolve; });
        };

        ok.addEventListener('click', () => dialog.close('ok'));
        document.getElementById('appConfirmCancel').addEventListener('click', () => dialog.close());
        // The dialog itself has no padding, so a click that lands on it is on the backdrop.
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => {
            if (settle) { settle(dialog.returnValue === 'ok'); settle = null; }
        });

        // Any form marked data-confirm / data-confirm-title waits for a yes.
        // Capture phase + stopImmediatePropagation, so the form's own submit
        // handlers (button disabling, the restore progress fetch) don't run
        // until the officer has confirmed; requestSubmit() then replays the
        // submit for them.
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (! (form.dataset.confirm || form.dataset.confirmTitle) || form.dataset.confirmed) return;

            e.preventDefault();
            e.stopImmediatePropagation();
            const submitter = e.submitter;

            window.confirmAction({
                title: form.dataset.confirmTitle,
                message: form.dataset.confirm,
                ok: form.dataset.confirmOk,
                tone: form.dataset.confirmTone,
            }).then(yes => {
                if (! yes) return;
                form.dataset.confirmed = '1';
                if (form.requestSubmit) form.requestSubmit(submitter || undefined);
                else form.submit();
                delete form.dataset.confirmed;
            });
        }, true);
    })();
</script>
