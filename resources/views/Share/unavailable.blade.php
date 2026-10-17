{{-- A share link that doesn't open anything (expired, turned off, or never existed) - see ShareLinkController::show(). --}}
@extends('Share.layout')

@section('title', 'Link not available')
@section('page-class', 'narrow')

@section('content')
<div class="card gone">
  <h1>This link no longer works</h1>
  <p>It may have expired, or it was turned off. Ask the person who sent it to share the file again.</p>
</div>
@endsection
