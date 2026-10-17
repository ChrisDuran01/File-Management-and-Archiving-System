{{-- One shared file, for someone without an account - see ShareLinkController::show(). --}}
@extends('Share.layout')

@section('title', $file->filename)

@section('content')
<div class="card">
  <div class="file-head">
    <span class="chip {{ $tone }}">{{ $ext }}</span>
    <div>
      <h1 class="file-name">{{ $file->filename }}</h1>
      <p class="file-meta">{{ $size }}{{ $link->creator ? ' · Shared by ' . $link->creator->name : '' }}</p>
    </div>
  </div>

  @if ($file->is_pending_approval)
    <div class="note amber" role="note">
      <strong>Draft - subject for approval</strong>
      This file has not been approved yet, so it is not official.
    </div>
  @endif

  @if ($preview === 'image')
    <img class="preview" src="{{ route('share.file', $token) }}" alt="{{ $file->filename }}">
  @elseif ($preview === 'pdf')
    <iframe class="preview" src="{{ route('share.file', $token) }}" title="{{ $file->filename }}"></iframe>
  @endif

  <div class="actions">
    <a class="btn primary" href="{{ route('share.file', ['token' => $token, 'download' => 1]) }}">Download</a>
    @if ($ext === 'pdf' && $preview !== 'pdf')
      <a class="btn" href="{{ route('share.file', $token) }}" target="_blank" rel="noopener noreferrer">Open</a>
    @endif
  </div>

  <p class="until">This link works until {{ $link->expires_at->format('F j, Y, g:i A') }}.</p>
</div>
@endsection
