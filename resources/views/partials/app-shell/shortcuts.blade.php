{{--
    "Keyboard shortcuts" list, opened with ? anywhere (not while typing) or
    from the account menu (partials/app-shell/scripts). Added 2026-10-25
    because nothing in the system told officers the keys existed. Keep it
    in step with the "Using the keyboard" section of resources/help/officer-guide.md.
--}}
<dialog class="kb-dialog" id="shortcutsDialog" aria-labelledby="shortcutsTitle">
    <div class="kb-inner">
        <div class="kb-head">
            <h2 id="shortcutsTitle">Keyboard shortcuts</h2>
            <button type="button" class="kb-close" data-kb-close aria-label="Close keyboard shortcuts">&times;</button>
        </div>

        <section class="kb-section" aria-labelledby="kbAnywhere">
            <h3 id="kbAnywhere">Anywhere</h3>
            <dl class="kb-list">
                <div><dt><kbd class="kb-key">?</kbd></dt><dd>Show this list</dd></div>
                <div><dt><kbd class="kb-key">/</kbd></dt><dd>Jump to the search box</dd></div>
                <div><dt><kbd class="kb-key">[</kbd></dt><dd>Collapse or expand the menu (on a laptop)</dd></div>
                <div><dt><kbd class="kb-key">Esc</kbd></dt><dd>Close a menu, a window or this list</dd></div>
            </dl>
        </section>

        <section class="kb-section" aria-labelledby="kbGo">
            <h3 id="kbGo">Go to a page: press G, then the letter</h3>
            <dl class="kb-list kb-go-grid">
                @foreach (\App\Support\OfficerMenu::groups(Auth::user()) as $items)
                    @foreach ($items as $item)
                        <div><dt><kbd class="kb-key">G</kbd> <kbd class="kb-key">{{ strtoupper($item['key']) }}</kbd></dt><dd>{{ $item['name'] }}</dd></div>
                    @endforeach
                @endforeach
            </dl>
        </section>

        <section class="kb-section" aria-labelledby="kbScroll">
            <h3 id="kbScroll">Scrolling the page</h3>
            <dl class="kb-list">
                <div><dt><kbd class="kb-key">↓</kbd> <kbd class="kb-key">↑</kbd></dt><dd>Scroll a little</dd></div>
                <div><dt><kbd class="kb-key">Page Down</kbd> <kbd class="kb-key">Page Up</kbd></dt><dd>Scroll one screen</dd></div>
                <div><dt><kbd class="kb-key">Space</kbd></dt><dd>Scroll one screen down</dd></div>
                <div><dt><kbd class="kb-key">Home</kbd> <kbd class="kb-key">End</kbd></dt><dd>Go to the top or the bottom</dd></div>
            </dl>
        </section>

        <section class="kb-section" aria-labelledby="kbMove">
            <h3 id="kbMove">Moving around</h3>
            <dl class="kb-list">
                <div><dt><kbd class="kb-key">Tab</kbd></dt><dd>Next link, button or box (Shift+Tab goes back)</dd></div>
                <div><dt><kbd class="kb-key">Enter</kbd></dt><dd>Open the highlighted link or press the button</dd></div>
                <div><dt><kbd class="kb-key">Tab</kbd> then <kbd class="kb-key">Enter</kbd></dt><dd>Right after a page opens: skip the menu and go to the page</dd></div>
            </dl>
        </section>

        <p class="kb-foot">Shortcuts don't work while you are typing in a box. Press Esc first.</p>
    </div>
</dialog>
