@extends('OfficerApp.layout', ['tab' => 'home'])

@section('title', 'Notifications · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    <a href="{{ route('app.home') }}" class="icon-btn" aria-label="Back to Home">@include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])</a>
    <div class="app-bar-text">
        <div class="app-bar-title">Notifications</div>
    </div>
    @if ($notifications->where('read', false)->isNotEmpty())
        <button type="button" class="btn text small" data-read-all style="margin-right: 12px">Mark all read</button>
    @endif
</header>
@endsection

@section('content')

@if ($notifications->isEmpty())
    <div class="empty"><strong>No notifications</strong>You will see it here when someone adds a file to one of your folders.</div>
@else
    <div class="card list">
        @foreach ($notifications as $n)
            @php $tagName = $n['link'] ? 'a' : 'div'; @endphp
            <{{ $tagName }} @if ($n['link']) href="{{ $n['link'] }}" data-notification="{{ $n['id'] }}" @endif class="row" style="align-items: flex-start">
                <span class="unread-dot {{ $n['read'] ? 'is-read' : '' }}" style="margin-top: 7px" @unless ($n['read']) role="img" aria-label="Unread" @endunless></span>
                <span class="row-text">
                    <span class="row-name" style="white-space: normal; {{ $n['read'] ? 'font-weight: 400' : '' }}">{{ $n['title'] }}</span>
                    @if ($n['message'])
                        <span class="row-snippet">{{ $n['message'] }}</span>
                    @endif
                    <span class="row-meta">{{ $n['when'] }}{{ $n['website'] ? ' · Opens on the website' : '' }}</span>
                </span>
            </{{ $tagName }}>
        @endforeach
    </div>
@endif

@endsection
