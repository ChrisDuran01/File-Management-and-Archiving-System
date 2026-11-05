@extends('twoFactor.layout')
@section('title', 'Save your recovery codes')
@section('width', '480px')

@section('styles')
    .codes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; margin: 0 0 1rem; padding: 0; list-style: none; }
    .codes li { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: 1rem; letter-spacing: .04em;
                text-align: center; background: #F3F6F4; border: 1px solid var(--border); border-radius: .5rem; padding: .55rem .4rem; }
    .row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin-bottom: 1rem; }
    .row .btn { padding: .65rem .4rem; font-size: .85rem; }
    @media (max-width: 380px) { .codes { grid-template-columns: 1fr; } .row { grid-template-columns: 1fr; } }
    @media print {
      body { background: #fff; }
      .card { box-shadow: none; border: none; }
      .row, .continue, .note { display: none !important; }
    }
@endsection

@section('content')
  @php
      $phone = \App\Http\Middleware\EnsurePhone::isPhone(request());
      $next = $phone ? route('app.home') : url(\App\Support\OfficerMenu::dashboard($user));
  @endphp

  <h1>Save your recovery codes</h1>
  <p class="lead">If you lose your phone, each of these signs you in once instead of the app's code. They are shown only now: print them, download them, or write them down, and keep them somewhere other than your phone.</p>

  <ul class="codes" id="codes">
    @foreach ($codes as $code)
      <li>{{ $code }}</li>
    @endforeach
  </ul>

  <div class="row">
    <button type="button" class="btn soft" data-copy><i class="fas fa-copy" aria-hidden="true"></i> Copy</button>
    <a class="btn soft" download="QSU-FMAS recovery codes.txt"
       href="data:text/plain;charset=utf-8,{{ rawurlencode("QSU-FMAS recovery codes for {$user->email}\r\nEach works once.\r\n\r\n" . implode("\r\n", $codes)) }}">
      <i class="fas fa-download" aria-hidden="true"></i> Download</a>
    <button type="button" class="btn soft" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> Print</button>
  </div>

  <a class="btn continue" href="{{ $next }}">I saved them - continue</a>
@endsection

@section('scripts')
<script>
  (function () {
    var copy = document.querySelector('[data-copy]');
    copy.addEventListener('click', function () {
      var text = Array.prototype.map.call(document.querySelectorAll('#codes li'), function (li) { return li.textContent; }).join('\n');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { copy.lastChild.textContent = ' Copied'; });
      }
    });
  })();
</script>
@endsection
