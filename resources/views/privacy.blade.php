<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Notice</title>
  @include('partials.favicon')
  <style>
    :root { --ink:#111; --muted:#6b6b6b; --border:#e5e5e5; --bg:#fafafa; --card:#fff; --accent:#1F6E4A; --accent-ink:#fff; --overlay:rgba(0,0,0,.45); }
    :root { color-scheme:light; }
    * { box-sizing:border-box; margin:0; padding:0; }
    /* simple thin scrollbar */
    * { scrollbar-width:thin; scrollbar-color:#c8c8c8 transparent; }
    *::-webkit-scrollbar { width:6px; height:6px; }
    *::-webkit-scrollbar-track { background:transparent; }
    *::-webkit-scrollbar-thumb { background:#c8c8c8; border-radius:3px; }
    *::-webkit-scrollbar-thumb:hover { background:#a8a8a8; }
    body { font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Helvetica Neue',Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased; color:var(--ink); background:var(--bg); line-height:1.6; font-size:15px; }
    a { color:var(--accent); }
    .page { max-width:680px; margin:0 auto; padding:48px 16px 64px; }
    h1 { font-size:1.4rem; font-weight:600; margin-bottom:4px; }
    .meta { color:var(--muted); font-size:.82rem; margin-bottom:28px; }
    .back { display:inline-block; margin-top:28px; font-size:.88rem; }

    /* acknowledgment modal */
    .overlay { position:fixed; inset:0; background:var(--overlay); display:flex; align-items:center; justify-content:center; padding:16px; }
    body.locked { overflow:hidden; }
    .modal { background:var(--card); border:1px solid var(--border); border-radius:12px; width:100%; max-width:440px; max-height:calc(100vh - 32px); display:flex; flex-direction:column; padding:28px 26px 22px; overflow:hidden; }
    .modal h1 { font-size:1.15rem; margin-bottom:6px; }
    .modal p.lead { color:var(--muted); font-size:.88rem; margin-bottom:14px; }
    .modal ul.sum { margin:0 0 12px 18px; }
    .modal ul.sum li { font-size:.88rem; margin-bottom:4px; }
    .toggle { background:none; border:none; padding:0; font:inherit; font-size:.85rem; color:var(--accent); text-decoration:underline; cursor:pointer; text-align:left; }
    .full-text { display:none; margin-top:12px; padding:14px; border:1px solid var(--border); border-radius:8px; overflow-y:auto; min-height:0; }
    .full-text.open { display:block; flex:1 1 auto; }
    .check { display:flex; gap:10px; align-items:flex-start; margin:18px 0 4px; font-size:.88rem; cursor:pointer; }
    .check input { width:16px; height:16px; margin-top:3px; accent-color:var(--accent); flex-shrink:0; }
    .err { border:1px solid var(--ink); border-radius:8px; padding:8px 10px; font-size:.84rem; margin-top:14px; }
    .actions { display:flex; gap:10px; align-items:center; margin-top:18px; }
    .btn { background:var(--accent); color:var(--accent-ink); border:1px solid var(--accent); border-radius:8px; padding:9px 18px; font:inherit; font-size:.88rem; font-weight:500; cursor:pointer; }
    .btn:disabled { opacity:.35; cursor:not-allowed; }
    .btn-plain { background:none; color:var(--muted); border-color:var(--border); }
  </style>
</head>
<body>
@if ($pending)
  <script>document.body.classList.add('locked');</script>
  <div class="overlay" role="dialog" aria-modal="true" aria-labelledby="pn-title">
    <form method="POST" action="{{ route('privacy.acknowledge.submit') }}" class="modal">
      @csrf
      <h1 id="pn-title">Privacy Notice</h1>
      <p class="lead">Before you continue, please note how your information is handled in this system.</p>
      <ul class="sum">
        <li>We keep your name, email, position, and an activity log (actions and IP address).</li>
        <li>Questions you type into the Help Assistant are sent to Google (Gemini).</li>
        <li>Activity logs are deleted after {{ number_format(config('retention.activity_log_days')) }} days.</li>
        <li>You may access, correct, or object to the use of your data.</li>
      </ul>
      <button type="button" class="toggle" id="pn-toggle" aria-expanded="false">Read the full notice</button>
      <div class="full-text" id="pn-full">
        @include('partials.privacy-content')
      </div>

      @error('acknowledge')
        <div class="err">{{ $message }}</div>
      @enderror

      <label class="check">
        <input type="checkbox" name="acknowledge" value="1" id="pn-check">
        <span>I have read and understood the Privacy Notice.</span>
      </label>

      <div class="actions">
        <button type="submit" class="btn" id="pn-continue" disabled>Continue</button>
        <button type="submit" class="btn btn-plain" form="pn-logout" formnovalidate>Sign out</button>
      </div>
    </form>
    <form id="pn-logout" method="POST" action="{{ route('logout') }}">@csrf</form>
  </div>
  <script>
    document.getElementById('pn-check').addEventListener('change', function () {
      document.getElementById('pn-continue').disabled = !this.checked;
    });
    document.getElementById('pn-toggle').addEventListener('click', function () {
      var box = document.getElementById('pn-full');
      var open = box.classList.toggle('open');
      this.setAttribute('aria-expanded', open);
      this.textContent = open ? 'Hide the full notice' : 'Read the full notice';
    });
  </script>
@else
  <div class="page">
    <h1>Privacy Notice</h1>
    <p class="meta">Version {{ config('privacy.version') }} &middot; Data Privacy Act of 2012 (RA 10173)</p>
    @include('partials.privacy-content')
    <a href="{{ url('/login') }}" class="back">&larr; Back to sign in</a>
  </div>
@endif
</body>
</html>
