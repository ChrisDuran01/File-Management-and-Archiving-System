{{-- Public Student Government page: announcements, documents, officers, about and contact. --}}
@extends('Student.home')

@section('content')
@php
    $logo = $siteSettings->logo_path ? asset('storage/'.$siteSettings->logo_path) : asset('images/SG-logo.png');
    $heroPhoto = $siteSettings->hero_background_path ? asset('storage/'.$siteSettings->hero_background_path) : null;
    $latest = $announcements->first();
    $hasVision = $siteSettings->vision || $siteSettings->vision_image_path;
    $hasMission = $siteSettings->mission || $siteSettings->mission_image_path;
    $hasHymn = $siteSettings->hymn || $siteSettings->hymn_image_path;
    $hasAbout = $hasVision || $hasMission || $hasHymn;
    $useTabs = $branches->count() > 1;
    $officerCount = $branches->sum(fn ($branch) => $branch->tiers->flatten(1)->count());
    $anLimit = 6;   // announcements shown before "Show all"
    $docLimit = 8;  // documents shown before "Show all"
@endphp

<a href="#main" class="skip-link">Skip to content</a>

{{-- ============================ Top bar ============================ --}}
<div class="topbar">
    <div class="wrap topbar-inner">
        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> Quirino State University · Diffun Campus</span>
        <a href="mailto:sg@qsu.edu.ph"><i class="bi bi-envelope" aria-hidden="true"></i> sg@qsu.edu.ph</a>
    </div>
</div>

{{-- ============================== Header ============================== --}}
<header class="site-header" id="site-header">
    <div class="wrap header-inner">
        <a href="#top" class="brand">
            <img src="{{ $logo }}" alt="" class="brand-logo" width="42" height="42">
            <span class="brand-text">
                <strong>QSU Student Government</strong>
                <small>Diffun Campus</small>
            </span>
        </a>

        <nav class="site-nav" id="site-nav" aria-label="Page sections">
            <a href="#announcements">Announcements <span id="announcementsBadge" class="nav-badge" data-unread-badge hidden></span></a>
            <a href="#documents">Documents</a>
            @if($branches->isNotEmpty())<a href="#officers">Officers</a>@endif
            @if($hasAbout)<a href="#about">About</a>@endif
            <a href="#contact" class="nav-cta">Contact us</a>
            {{-- Optional app install (phone menu only). Shown by the script where the device supports it. --}}
            <button type="button" class="nav-app" data-install-open hidden><i class="bi bi-phone" aria-hidden="true"></i> Get the app</button>
        </nav>

        <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="site-nav">
            <span></span><span></span><span></span>
        </button>

        {{-- Installed app only: no browser reload button there. --}}
        <button type="button" class="app-bar-btn app-only" id="appRefresh" aria-label="Refresh">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
        </button>
    </div>
</header>

{{-- =============================== Hero =============================== --}}
<section class="hero {{ $heroPhoto ? 'has-photo' : '' }}" id="top"
    @if($heroPhoto) style="--hero-photo: url('{{ $heroPhoto }}');" @endif>
    <div class="wrap">
        <div class="hero-inner">
            <p class="eyebrow">Official website</p>
            <h1>QSU Diffun <span class="hl">Student Government</span></h1>
            <p class="hero-lede">Stay updated, find official documents, and reach the officers who represent you, all in one place.</p>

            <div class="hero-actions">
                <a href="#announcements" class="btn btn-light">Read the latest news <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a href="#contact" class="btn btn-ghost">Message us</a>
            </div>

            @if($latest)
                <a href="#announcements" class="hero-latest" id="heroLatest">
                    <span class="hero-latest-tag">Latest</span>
                    <span class="hero-latest-title">{{ $latest->title }}</span>
                    <span class="hero-latest-date">{{ $latest->created_at->diffForHumans() }}</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    </div>
</section>

{{-- Quick links: cards that overlap the bottom of the hero. --}}
<nav class="quick" aria-label="Quick links">
    <div class="wrap quick-grid">
        <a href="#announcements" class="quick-card">
            <span class="quick-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
            <strong>Announcements</strong>
            <span>News, events and reminders</span>
        </a>
        <a href="#documents" class="quick-card">
            <span class="quick-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
            <strong>Documents</strong>
            <span>Resolutions, reports and records</span>
        </a>
        @if($branches->isNotEmpty())
            <a href="#officers" class="quick-card">
                <span class="quick-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <strong>Officers</strong>
                <span>Meet the people serving you</span>
            </a>
        @endif
        <a href="#contact" class="quick-card">
            <span class="quick-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
            <strong>Contact us</strong>
            <span>Questions, concerns or ideas</span>
        </a>
    </div>
</nav>

<main id="main" tabindex="-1">

    {{-- ========================== Announcements ========================== --}}
    <section class="section" id="announcements" aria-labelledby="announcements-title">
        <div class="wrap">
            <div class="sec-head sec-head-center">
                <div>
                    <p class="eyebrow">Stay informed</p>
                    <h2 id="announcements-title">Latest announcements</h2>
                    <p>News, events and reminders from the Student Government.</p>
                </div>
                {{-- Browser push opt-in. Hidden until the script confirms the browser supports it. --}}
                <button type="button" id="pushToggle" class="btn btn-soft btn-sm push-toggle" hidden
                        title="Get a notification on this device when a new announcement or public document is posted. No account or personal details needed.">
                    <i class="bi bi-bell" aria-hidden="true"></i> <span id="pushToggleLabel">Notify me</span>
                </button>
            </div>

            @if($announcements->isEmpty())
                <div class="empty">
                    <i class="bi bi-megaphone" aria-hidden="true"></i>
                    <strong>No announcements yet</strong>
                    <span>Turn on notifications to hear about the first one.</span>
                </div>
            @else
                <div class="an-grid" id="announcementList">
                    @foreach($announcements as $announcement)
                        <article class="an-card {{ $announcement->images->isNotEmpty() ? 'has-media' : '' }}"
                                 data-id="{{ $announcement->id }}" data-ts="{{ $announcement->created_at->timestamp }}" @if($loop->index >= $anLimit) data-extra hidden @endif>
                            {{-- Cover: the post's photos (tap to view), or a pattern in the SG colours. --}}
                            <div class="an-top">
                                @if($announcement->images->isNotEmpty())
                                    @php
                                        $photos = $announcement->images->values();
                                        // A lone photo keeps its own shape in the pop-up; knowing its size
                                        // lets the browser hold the space before the picture has loaded.
                                        $size = $photos->count() === 1 ? @getimagesize(Storage::disk('public')->path($photos[0]->path)) : null;
                                    @endphp
                                    <div class="an-media">
                                        {{-- On the card only the first photo shows; the pop-up shows up to four
                                             tiles with "+N" for the rest. Any tile opens the photo viewer. --}}
                                        <div class="pg pg-{{ min($photos->count(), 4) }}" data-title="{{ $announcement->title }}"
                                             data-photos="{{ $photos->map(fn ($photo) => route('announcements.image', $photo->id))->toJson() }}">
                                            @foreach($photos->take(4) as $photo)
                                                <button type="button" class="pg-tile" data-index="{{ $loop->index }}" aria-haspopup="dialog"
                                                        aria-label="View photo {{ $loop->iteration }} of {{ $photos->count() }} for {{ $announcement->title }}">
                                                    <img src="{{ route('announcements.image', $photo->id) }}" alt="" loading="lazy" draggable="false"
                                                         @if($size) width="{{ $size[0] }}" height="{{ $size[1] }}" @endif>
                                                    @if($loop->last && $photos->count() > 4)
                                                        <span class="pg-more" aria-hidden="true">+{{ $photos->count() - 4 }}</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                    @if($photos->count() > 1)
                                        <span class="an-count" aria-hidden="true"><i class="bi bi-images"></i> {{ $photos->count() }}</span>
                                    @endif
                                @else
                                    <div class="an-cover an-cover-{{ $announcement->id % 3 }}" aria-hidden="true">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                @endif
                                <span class="an-date" aria-hidden="true"><b>{{ $announcement->created_at->format('d') }}</b><span>{{ $announcement->created_at->format('M') }}</span></span>
                            </div>

                            <div class="an-text">
                                <div class="an-meta">
                                    <time datetime="{{ $announcement->created_at->toIso8601String() }}" title="{{ $announcement->created_at->format('M d, Y g:i A') }}">{{ $announcement->created_at->format('M j, Y') }}</time>
                                    @if($announcement->created_at->diffInHours(now()) < 72)<span class="chip">New</span>@endif
                                </div>
                                <h3 class="an-title">{{ $announcement->title }}</h3>
                                <div class="an-body">{!! $announcement->body !!}</div>

                                <div class="an-actions">
                                    <button type="button" class="link-arrow an-see-more" aria-haspopup="dialog" hidden>
                                        <span class="u">Read more</span><span class="sr-only">: {{ $announcement->title }}</span> <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </button>
                                    @if($announcement->attachment_path)
                                        <a href="{{ route('announcements.download', $announcement->id) }}" class="link-arrow an-attach" data-an-id="{{ $announcement->id }}">
                                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                                            <span class="u an-attach-name">{{ $announcement->attachment_original_name ?? 'Attachment' }}</span>
                                            <span class="sr-only">(download)</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($announcements->count() > $anLimit)
                    <div class="more-row">
                        <button type="button" class="btn btn-primary" id="anMore" aria-expanded="false" aria-controls="announcementList" data-total="{{ $announcements->count() }}">
                            Show all {{ $announcements->count() }} announcements
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- ============================ At a glance ============================ --}}
    <section class="stats" aria-label="At a glance">
        <div class="wrap stats-grid">
            <div class="stat">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                <div><strong>{{ $announcements->count() }}</strong><span>{{ Str::plural('Announcement', $announcements->count()) }}</span></div>
            </div>
            <div class="stat">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                <div><strong>{{ $files->count() }}</strong><span>Public {{ Str::plural('document', $files->count()) }}</span></div>
            </div>
            @if($officerCount)
                <div class="stat">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <div><strong>{{ $officerCount }}</strong><span>{{ Str::plural('Officer', $officerCount) }} this term</span></div>
                </div>
                <div class="stat">
                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                    <div><strong>{{ $branches->count() }}</strong><span>{{ Str::plural('Branch', $branches->count()) }} of government</span></div>
                </div>
            @endif
        </div>
    </section>

    {{-- ============================ Documents ============================ --}}
    <section class="section section-tint" id="documents" aria-labelledby="documents-title">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <p class="eyebrow">Transparency</p>
                    <h2 id="documents-title">Public documents</h2>
                    <p>Official records the Student Government has made public. Open one to preview or download it.</p>
                </div>
                @if($files->isNotEmpty())
                    <div class="search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="docSearch" placeholder="Search documents" aria-label="Search documents" autocomplete="off">
                    </div>
                @endif
            </div>

            @if($docFamilies->count() > 1)
                <div class="filters" role="group" aria-label="Filter documents by type">
                    <button type="button" class="filter" data-family="all" aria-pressed="true">All <span>{{ $files->count() }}</span></button>
                    @foreach($docFamilies as $key => $family)
                        <button type="button" class="filter" data-family="{{ $key }}" aria-pressed="false">{{ $family['label'] }} <span>{{ $family['count'] }}</span></button>
                    @endforeach
                </div>
            @endif

            @if($files->isEmpty())
                <div class="empty">
                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    <strong>No public documents yet</strong>
                    <span>Documents the Student Government shares will appear here.</span>
                </div>
            @else
                <ul class="doc-list" id="docList">
                    @foreach($files as $file)
                        <li class="doc" data-family="{{ $file->family }}"
                            data-search="{{ strtolower($file->filename.' '.$file->school_year.' '.$file->created_at->format('M d Y')) }}"
                            @if($loop->index >= $docLimit) data-extra hidden @endif>
                            <button type="button" class="doc-btn" data-id="{{ $file->id }}" data-name="{{ $file->filename }}" aria-haspopup="dialog">
                                <span class="doc-type t-{{ $file->family }}" aria-hidden="true">{{ strtoupper($file->ext ?: 'file') }}</span>
                                <span class="doc-info">
                                    <span class="doc-name">{{ $file->filename }}</span>
                                    <span class="doc-meta">
                                        <span>{{ $file->created_at->format('M j, Y') }}</span>
                                        @if($file->size_label) &middot; <span>{{ $file->size_label }}</span> @endif
                                        @if($file->school_year) &middot; <span>SY {{ $file->school_year }}</span> @endif
                                    </span>
                                </span>
                                <span class="doc-go" aria-hidden="true"><i class="bi bi-eye"></i></span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="empty" id="docEmpty" hidden>
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <strong>No documents match your search</strong>
                    <button type="button" class="link-arrow" id="docClear"><span class="u">Clear search and filters</span></button>
                </div>
                <p class="sr-only" id="docStatus" aria-live="polite"></p>

                @if($files->count() > $docLimit)
                    <div class="more-row" id="docMoreRow">
                        <button type="button" class="btn btn-primary" id="docMore" aria-expanded="false" aria-controls="docList" data-total="{{ $files->count() }}">
                            Show all {{ $files->count() }} documents
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- ============================= Officers ============================= --}}
    @if($branches->isNotEmpty())
        <section class="section" id="officers" aria-labelledby="officers-title">
            <div class="wrap">
                <div class="sec-head sec-head-center">
                    <div>
                        <p class="eyebrow">Meet the team</p>
                        <h2 id="officers-title">Your Student Government</h2>
                        <p>The officers serving you this term, from each branch's head down.</p>
                    </div>
                </div>

                @if($useTabs)
                    <div class="tabs" role="tablist" aria-label="Branches of the Student Government">
                        @foreach($branches as $branch)
                            <button type="button" class="tab" role="tab" id="tab-{{ $branch->key }}" aria-controls="panel-{{ $branch->key }}"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}" @unless($loop->first) tabindex="-1" @endunless>
                                {{ $branch->name }} <span class="tab-count">{{ $branch->tiers->flatten(1)->count() }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @foreach($branches as $branch)
                    <div class="branch" id="panel-{{ $branch->key }}"
                         @if($useTabs) role="tabpanel" aria-labelledby="tab-{{ $branch->key }}" tabindex="0" @unless($loop->first) hidden @endunless @endif>
                        <p class="branch-about">
                            @unless($useTabs)<strong>{{ $branch->name }}.</strong>@endunless
                            {{ $branch->about }}
                        </p>

                        @foreach($branch->tiers as $tier => $people)
                            <div class="tier tier-{{ $tier }}">
                                @foreach($people as $officer)
                                    <figure class="person">
                                        {{-- Initials sit under the photo, so a slow or missing photo still shows something. --}}
                                        <span class="avatar" aria-hidden="true">
                                            {{ $officer->initials }}
                                            @if($officer->photo)
                                                <img src="{{ asset('storage/'.$officer->photo) }}" alt="" loading="lazy" width="128" height="128">
                                            @endif
                                        </span>
                                        <figcaption>
                                            <strong>{{ $officer->name }}</strong>
                                            <span>{{ $officer->position }}</span>
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ========================= Call to action ========================= --}}
    <section class="cta" aria-labelledby="cta-title">
        <div class="wrap">
            <div class="cta-card">
                <div>
                    <h2 id="cta-title">Have a concern, question or idea?</h2>
                    <p>Your Student Government is listening. Send a message and an officer will reply to your email.</p>
                </div>
                <a href="#contact" class="btn btn-light">Message us <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>

    {{-- =============================== About =============================== --}}
    @if($hasAbout)
        <section class="section section-tint" id="about" aria-labelledby="about-title">
            <div class="wrap">
                <div class="sec-head sec-head-center">
                    <div>
                        <p class="eyebrow">Who we are</p>
                        <h2 id="about-title">Vision and mission</h2>
                        <p>The vision and mission of Quirino State University, which guide its Student Government.</p>
                    </div>
                </div>

                @if($hasVision || $hasMission)
                    <div class="about-grid">
                        @if($hasVision)
                            <article class="about-card">
                                <span class="about-icon" aria-hidden="true"><i class="bi bi-eye"></i></span>
                                <h3 class="label">Vision</h3>
                                @if($siteSettings->vision_image_path)
                                    <img src="{{ asset('storage/'.$siteSettings->vision_image_path) }}" alt="QSU vision statement" loading="lazy">
                                @else
                                    <p class="about-text">{{ $siteSettings->vision }}</p>
                                @endif
                            </article>
                        @endif
                        @if($hasMission)
                            <article class="about-card">
                                <span class="about-icon" aria-hidden="true"><i class="bi bi-flag"></i></span>
                                <h3 class="label">Mission</h3>
                                @if($siteSettings->mission_image_path)
                                    <img src="{{ asset('storage/'.$siteSettings->mission_image_path) }}" alt="QSU mission statement" loading="lazy">
                                @else
                                    <p class="about-text">{{ $siteSettings->mission }}</p>
                                @endif
                            </article>
                        @endif
                    </div>
                @endif

                @if($hasHymn)
                    <details class="hymn">
                        <summary>
                            <span class="hymn-title"><i class="bi bi-music-note-beamed" aria-hidden="true"></i> QSU Hymn</span>
                            <span class="hymn-hint"><span class="when-closed">Show lyrics</span><span class="when-open">Hide lyrics</span> <i class="bi bi-chevron-down" aria-hidden="true"></i></span>
                        </summary>
                        <div class="hymn-body">
                            @if($siteSettings->hymn_image_path)
                                <img src="{{ asset('storage/'.$siteSettings->hymn_image_path) }}" alt="QSU Hymn lyrics" loading="lazy">
                            @else
                                <p>{{ $siteSettings->hymn }}</p>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        </section>
    @endif

    {{-- ============================== Contact ============================== --}}
    <section class="section" id="contact" aria-labelledby="contact-title">
        <div class="wrap">
            <div class="contact">
                <div class="contact-intro">
                    <p class="eyebrow">Contact us</p>
                    <h2 id="contact-title">Get in touch</h2>
                    <p>Questions, concerns or ideas? Send the Student Government a message and an officer will reply to your email.</p>
                    <ul class="contact-points">
                        <li><i class="bi bi-clock" aria-hidden="true"></i><span>Replies within <strong>3–5 business days</strong></span></li>
                        <li><i class="bi bi-envelope" aria-hidden="true"></i><span>Prefer email? Write to <a href="mailto:sg@qsu.edu.ph">sg@qsu.edu.ph</a></span></li>
                        <li><i class="bi bi-shield-check" aria-hidden="true"></i><span>Your details are used only to reply to you. <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener">Privacy notice</a></span></li>
                    </ul>
                </div>

                <form id="feedbackForm" class="contact-form" method="POST" action="{{ route('send.message') }}">
                    @csrf

                    @if(session('success'))
                        <div class="notice notice-ok" id="contactNotice" role="status" tabindex="-1">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            <span>{{ session('success') }}. We'll reply to your email within 3–5 business days.</span>
                        </div>
                    @elseif($errors->any())
                        <div class="notice notice-err" id="contactNotice" role="alert" tabindex="-1">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span>Your message wasn't sent. Please fix the {{ Str::plural('field', $errors->count()) }} marked below.</span>
                        </div>
                    @endif

                    <div class="field-row">
                        <div class="field">
                            <label for="senderName">Full name</label>
                            <input type="text" name="name" id="senderName" autocomplete="name" value="{{ old('name') }}" maxlength="255" required
                                   @error('name') aria-invalid="true" aria-describedby="senderName-error" @enderror>
                            @error('name')<small class="field-error" id="senderName-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field">
                            <label for="senderEmail">Email</label>
                            <input type="email" name="email" id="senderEmail" autocomplete="email" inputmode="email" value="{{ old('email') }}" required
                                   @error('email') aria-invalid="true" aria-describedby="senderEmail-error" @enderror>
                            @error('email')<small class="field-error" id="senderEmail-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="studentId">Student ID</label>
                            <input type="text" name="student_id" id="studentId" value="{{ old('student_id') }}" maxlength="100" required
                                   @error('student_id') aria-invalid="true" aria-describedby="studentId-error" @enderror>
                            @error('student_id')<small class="field-error" id="studentId-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field">
                            <label for="subject">Subject</label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" maxlength="255" required
                                   @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                            @error('subject')<small class="field-error" id="subject-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="message">Message</label>
                        <textarea name="message" id="message" rows="5" required
                                  @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                        @error('message')<small class="field-error" id="message-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-foot">
                        <button type="submit" class="btn btn-primary" id="sendBtn">
                            <i class="bi bi-send" aria-hidden="true"></i> <span>Send message</span>
                        </button>
                        <small>All fields are required.</small>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

{{-- ============ Installed app: one screen per section, tabs at the bottom ============ --}}
<nav class="tabbar app-only" id="tabbar" aria-label="App sections">
    <a href="#announcements" data-tab="announcements">
        <span class="tab-icon"><i class="bi bi-megaphone ico-off" aria-hidden="true"></i><i class="bi bi-megaphone-fill ico-on" aria-hidden="true"></i></span>
        <span class="tab-label">News</span>
        <span class="nav-badge tab-badge" data-unread-badge hidden></span>
    </a>
    <a href="#documents" data-tab="documents">
        <span class="tab-icon"><i class="bi bi-file-earmark-text ico-off" aria-hidden="true"></i><i class="bi bi-file-earmark-text-fill ico-on" aria-hidden="true"></i></span>
        <span class="tab-label">Documents</span>
    </a>
    @if($branches->isNotEmpty())
        <a href="#officers" data-tab="officers">
            <span class="tab-icon"><i class="bi bi-people ico-off" aria-hidden="true"></i><i class="bi bi-people-fill ico-on" aria-hidden="true"></i></span>
            <span class="tab-label">Officers</span>
        </a>
    @endif
    @if($hasAbout)
        <a href="#about" data-tab="about">
            <span class="tab-icon"><i class="bi bi-info-circle ico-off" aria-hidden="true"></i><i class="bi bi-info-circle-fill ico-on" aria-hidden="true"></i></span>
            <span class="tab-label">About</span>
        </a>
    @endif
    <a href="#contact" data-tab="contact">
        <span class="tab-icon"><i class="bi bi-chat-dots ico-off" aria-hidden="true"></i><i class="bi bi-chat-dots-fill ico-on" aria-hidden="true"></i></span>
        <span class="tab-label">Contact</span>
    </a>
</nav>

{{-- Connection notice: offline, or showing the copy saved for offline use (see public/sw.js). --}}
<div class="net-bar" id="netBar" role="status" hidden>
    <i class="bi bi-wifi-off" aria-hidden="true"></i>
    <span id="netBarText"></span>
    <button type="button" class="net-bar-btn" id="netBarBtn" hidden>Refresh</button>
</div>

{{-- ============================== Footer ============================== --}}
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div class="footer-about">
            <div class="footer-brand">
                <img loading="lazy" decoding="async" src="{{ $logo }}" alt="" width="48" height="48">
                <div>
                    <strong>QSU Student Government</strong>
                    <span>Diffun Campus</span>
                </div>
            </div>
            <p>The official page of the Student Government of Quirino State University, Diffun Campus: announcements, public documents and the officers who serve you.</p>
        </div>

        <nav class="footer-links" aria-label="Footer">
            <h3>Explore</h3>
            <a href="#announcements">Announcements</a>
            <a href="#documents">Documents</a>
            @if($branches->isNotEmpty())<a href="#officers">Officers</a>@endif
            @if($hasAbout)<a href="#about">Vision and mission</a>@endif
            <a href="#contact">Contact us</a>
        </nav>

        <div class="footer-contact">
            <h3>Get in touch</h3>
            <ul>
                <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:sg@qsu.edu.ph">sg@qsu.edu.ph</a></li>
                <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Quirino State University, Diffun Campus</span></li>
                <li><i class="bi bi-shield-check" aria-hidden="true"></i><a href="{{ route('privacy.notice') }}">Privacy notice</a></li>
            </ul>
            <button type="button" class="footer-app" data-install-open hidden><i class="bi bi-phone" aria-hidden="true"></i> Get the app</button>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="wrap">&copy; {{ date('Y') }} QSU Student Government · Diffun Campus</div>
    </div>
</footer>

{{-- ======================== Document preview ======================== --}}
<dialog id="filePreviewModal" class="dlg file-dlg" aria-labelledby="modalFileName">
    <div class="dlg-head">
        <h3 id="modalFileName">Document preview</h3>
        <button type="button" class="dlg-close" data-close aria-label="Close preview"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="dlg-body">
        <div class="pv-pane pv-state" id="previewLoading"><div class="spinner" aria-hidden="true"></div><p>Loading preview…</p></div>
        <div class="pv-pane" id="pdfPreview" hidden><iframe id="pdfFrame" class="pv-frame" title="PDF preview"></iframe></div>
        <div class="pv-pane pv-center" id="imagePreview" hidden><img id="imageViewer" class="pv-image" alt=""></div>
        <div class="pv-pane" id="officePreview" hidden><iframe id="officeFrame" class="pv-frame" title="Document preview"></iframe></div>
        <div class="pv-pane" id="textPreview" hidden><pre id="textViewer" class="pv-text"></pre></div>
        <div class="pv-pane pv-state" id="genericPreview" hidden>
            <i class="bi bi-file-earmark" aria-hidden="true"></i>
            <p>This file type can't be previewed here.<br>Download it to open it on your device.</p>
        </div>
        <div class="pv-pane pv-state is-error" id="previewError" hidden>
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <p>We couldn't load this preview. Check your connection and try again.</p>
            <button type="button" class="btn btn-soft btn-sm" id="previewRetry">Try again</button>
        </div>
    </div>
    <div class="dlg-foot" id="previewFoot" hidden>
        <button type="button" class="btn btn-primary" id="downloadBtn"><i class="bi bi-download" aria-hidden="true"></i> <span>Download</span></button>
    </div>
</dialog>

{{-- ======================== Full announcement ======================== --}}
<dialog id="announcementModal" class="dlg an-dlg" aria-labelledby="anModalTitle">
    <button type="button" class="dlg-close dlg-close-float" data-close aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <div class="dlg-scroll" id="anModalBody"></div>
</dialog>

{{-- =========================== Photo viewer =========================== --}}
<dialog id="photoViewer" class="lb" aria-label="Photo viewer">
    <div class="lb-bar">
        <span class="lb-count" id="photoViewerCount" aria-live="polite"></span>
        <button type="button" class="lb-btn" data-close aria-label="Close photo"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="lb-stage" id="photoViewerStage">
        <div class="spinner" aria-hidden="true"></div>
        <img id="photoViewerImg" class="lb-img" alt="" draggable="false">
    </div>
    <button type="button" class="lb-btn lb-prev" id="photoViewerPrev" aria-label="Previous photo"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
    <button type="button" class="lb-btn lb-next" id="photoViewerNext" aria-label="Next photo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
</dialog>

{{-- ======================= Get the app (optional) =======================
     The website stays the main way in; this only explains how to add it to
     the home screen. The script shows the steps that fit the device. --}}
<dialog id="installDialog" class="dlg install-dlg" aria-labelledby="installTitle">
    <button type="button" class="dlg-close dlg-close-float" data-close aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <div class="dlg-scroll">
        <img src="{{ asset('icons/icon-192.png') }}" alt="" class="install-icon" width="72" height="72" loading="lazy">
        <h2 class="install-title" id="installTitle">Get the SG app</h2>
        <p class="install-lede">Add this page to your home screen and it opens like an app: full screen, one tap away, and even without internet. It's the same page, so the website works just as well.</p>
        <p class="install-note" data-install-note="push" hidden>
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span>On iPhone and iPad, notifications work only in the app. Add it to your Home Screen, open it from there, then tap <strong>Notify me</strong>.</span>
        </p>

        <ol class="install-steps" data-install-steps="ios" hidden>
            <li><span>Tap <strong>Share</strong> <i class="bi bi-box-arrow-up" aria-hidden="true"></i> in the browser's toolbar. On newer iPhones it may be inside the <strong>&hellip;</strong> menu.</span></li>
            <li><span>Choose <strong>Add to Home Screen</strong> <i class="bi bi-plus-square" aria-hidden="true"></i>. You may need to scroll down or tap <strong>View More</strong>.</span></li>
            <li><span>Tap <strong>Add</strong>, then open <strong>QSU SG</strong> from your Home Screen.</span></li>
        </ol>
        <ol class="install-steps" data-install-steps="ios-inapp" hidden>
            <li><span>This page is open inside another app, like Facebook or Messenger. Tap its <strong>&hellip;</strong> menu and choose <strong>Open in Safari</strong> or <strong>Open in browser</strong>.</span></li>
            <li><span>In Safari, tap <strong>Share</strong> <i class="bi bi-box-arrow-up" aria-hidden="true"></i>, then <strong>Add to Home Screen</strong>.</span></li>
            <li><span>Tap <strong>Add</strong>, then open <strong>QSU SG</strong> from your Home Screen.</span></li>
        </ol>
        <ol class="install-steps" data-install-steps="android" hidden>
            <li><span>Open the browser menu <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>.</span></li>
            <li><span>Tap <strong>Add to Home screen</strong> or <strong>Install app</strong>.</span></li>
            <li><span>Confirm, then open <strong>QSU SG</strong> from your home screen.</span></li>
        </ol>
        <ol class="install-steps" data-install-steps="android-inapp" hidden>
            <li><span>This page is open inside another app, like Facebook or Messenger. Tap its <strong>&#8942;</strong> menu and choose <strong>Open in Chrome</strong> or <strong>Open in browser</strong>.</span></li>
            <li><span>In Chrome, open the menu <i class="bi bi-three-dots-vertical" aria-hidden="true"></i> and tap <strong>Add to Home screen</strong> or <strong>Install app</strong>.</span></li>
            <li><span>Confirm, then open <strong>QSU SG</strong> from your home screen.</span></li>
        </ol>
        <ol class="install-steps" data-install-steps="mac" hidden>
            <li><span>In Safari's menu bar, choose <strong>File</strong> &rsaquo; <strong>Add to Dock</strong>.</span></li>
            <li><span>Click <strong>Add</strong>, then open <strong>QSU SG</strong> from the Dock.</span></li>
        </ol>
        <ol class="install-steps" data-install-steps="other" hidden>
            <li><span>Open your browser's menu.</span></li>
            <li><span>Choose <strong>Install</strong> or <strong>Add to Home screen</strong>. Not there? Open this page in Chrome, Edge or Safari.</span></li>
        </ol>

        <p class="install-extra">Already added it? Open <strong>QSU SG</strong> from your home screen.</p>
        <div class="install-actions">
            <button type="button" class="btn btn-primary" data-close>Got it</button>
        </div>
    </div>
</dialog>

<style>
    /* ==================================================================
       A school-organization look in the SG green. Gold is only an accent:
       label dashes, the active-menu underline, "New" and "Latest" tags,
       the president's ring. A photo hero with quick-link cards over its
       edge, news as cards, a stats band, team cards, a call-to-action
       card and a widget footer. Sections alternate white and a light
       green tint. Outline icons, Poppins for headings, Inter for reading.
       ================================================================== */
    :root {
        --green: #0f3d1f;
        --green-2: #145a2c;
        --green-3: #1d7a3e;
        --green-dark: #0a2a15;
        --gold: #f4b400;
        --green-soft: #e7f3ea;
        --green-light: #9ad9ab;
        --ink: #10231a;
        --text: #34443b;
        --muted: #617067;
        --faint: #8b988f;
        --bg: #ffffff;
        --tint: #f2f7f3;
        --tint-2: #e5eee7;
        --line: #e0e8e2;
        --line-2: #cdd8d0;
        --danger: #b42318;
        --head: 'Poppins', 'Inter', system-ui, sans-serif;
        --radius: 16px;
        --radius-sm: 10px;
        --wrap: 1180px;
        --header-h: 72px;
        --shadow: 0 8px 26px rgba(16, 35, 26, .08);
        --shadow-lg: 0 18px 44px rgba(16, 35, 26, .14);
        /* Small dot grid used as texture on the green blocks. */
        --dots-light: radial-gradient(rgba(255, 255, 255, .09) 1.2px, transparent 1.7px);
        --dots-dark: radial-gradient(rgba(16, 35, 26, .09) 1.2px, transparent 1.7px);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    [hidden] { display: none !important; }
    html { scroll-behavior: smooth; scroll-padding-top: calc(var(--header-h) + 12px); -webkit-text-size-adjust: 100%; }
    html.dialog-open { overflow: hidden; }
    body {
        background: var(--bg);
        color: var(--text);
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        overflow-x: clip;
    }
    img { max-width: 100%; display: block; }
    a { color: inherit; }
    button, input, textarea { font: inherit; color: inherit; }
    button { cursor: pointer; }
    a, button { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
    :focus-visible { outline: 3px solid var(--green-3); outline-offset: 2px; }
    /* On the dark green blocks the ring turns gold so it stays visible. */
    :is(.topbar, .hero, .stats, .cta-card, .contact-intro, .site-footer) :focus-visible { outline-color: var(--gold); }
    main:focus { outline: none; }

    .sr-only {
        position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
    }
    .skip-link {
        position: fixed; top: 12px; left: 12px; z-index: 300; padding: 10px 18px; border-radius: 999px;
        background: var(--green); color: #fff; font-weight: 600; text-decoration: none;
        transform: translateY(-200%); transition: transform .2s;
    }
    .skip-link:focus { transform: none; }

    .wrap {
        width: 100%;
        max-width: var(--wrap);
        margin: 0 auto;
        padding-left: max(20px, env(safe-area-inset-left));
        padding-right: max(20px, env(safe-area-inset-right));
    }

    /* Small label above a heading, with a gold dash. */
    .eyebrow {
        display: inline-flex; align-items: center; gap: 10px;
        color: var(--green-3); font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
    }
    .eyebrow::before { content: ''; width: 26px; height: 3px; border-radius: 3px; background: var(--gold); }

    /* ---------------------------- Buttons ---------------------------- */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        min-height: 48px; padding: 0 26px; border: 2px solid transparent; border-radius: 999px;
        font-size: .95rem; font-weight: 600; text-decoration: none; white-space: nowrap;
        transition: background-color .15s, border-color .15s, color .15s, opacity .15s, transform .15s;
    }
    .btn .bi-arrow-right { transition: transform .15s; }
    .btn:hover .bi-arrow-right { transform: translateX(3px); }
    .btn-sm { min-height: 40px; padding: 0 18px; font-size: .86rem; }
    .btn-light { background: #fff; color: var(--green); }
    .btn-light:hover { background: var(--green-soft); }
    .btn-primary { background: var(--green); color: #fff; }
    .btn-primary:hover { background: var(--green-2); }
    .btn-ghost { border-color: rgba(255, 255, 255, .75); color: #fff; }
    .btn-ghost:hover { background: rgba(255, 255, 255, .12); border-color: #fff; }
    .btn-soft { background: var(--bg); border-color: var(--line-2); color: var(--green); }
    .btn-soft:hover { border-color: var(--green-3); background: var(--tint); }
    .btn:disabled { opacity: .6; cursor: default; }
    .push-toggle.is-on { background: var(--green); border-color: var(--green); color: #fff; }

    /* Text links that act like buttons: 44px tall, underline only on the words. */
    .link-arrow {
        display: inline-flex; align-items: center; gap: 6px; min-height: 44px; min-width: 0;
        padding: 0; border: 0; background: none; color: var(--green-3);
        font-size: .92rem; font-weight: 600; text-decoration: none; transition: color .15s;
    }
    .link-arrow .u {
        text-decoration: underline; text-decoration-thickness: 2px; text-underline-offset: 5px;
        text-decoration-color: rgba(29, 122, 62, .3); transition: text-decoration-color .15s;
    }
    .link-arrow .bi-arrow-right { transition: transform .15s; }
    .link-arrow:hover { color: var(--green); }
    .link-arrow:hover .u { text-decoration-color: currentColor; }
    .link-arrow:hover .bi-arrow-right { transform: translateX(3px); }

    /* ----------------------------- Top bar ----------------------------- */
    .topbar { background: var(--green-dark); color: rgba(255, 255, 255, .8); font-size: .82rem; }
    .topbar-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 38px; }
    .topbar span,
    .topbar a { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .topbar i { color: var(--green-light); }
    .topbar a:hover { color: #fff; }
    @media (max-width: 760px) { .topbar { display: none; } }

    /* ----------------------------- Header ----------------------------- */
    .site-header {
        position: sticky; top: 0; z-index: 100; height: var(--header-h);
        background: rgba(255, 255, 255, .97); color: var(--ink);
        box-shadow: 0 1px 0 var(--line); transition: box-shadow .2s;
    }
    .site-header.is-solid { box-shadow: 0 8px 24px rgba(16, 35, 26, .09); }
    .header-inner { height: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .brand { display: inline-flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
    .brand-logo { width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%; object-fit: cover; background: #fff; box-shadow: 0 0 0 2px var(--green-soft); }
    .brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
    .brand-text strong { color: var(--green); font-family: var(--head); font-size: 1rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .brand-text small { color: var(--muted); font-size: .7rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }

    .site-nav { display: flex; align-items: center; gap: 2px; }
    .site-nav a {
        position: relative; display: inline-flex; align-items: center; gap: 6px;
        min-height: 44px; padding: 0 14px; border-radius: 999px;
        color: var(--ink); font-size: .93rem; font-weight: 500; text-decoration: none;
        transition: color .15s, background-color .15s;
    }
    .site-nav a:hover,
    .site-nav a[aria-current] { color: var(--green-3); }
    .site-nav a[aria-current] { font-weight: 600; }
    .site-nav a[aria-current]::after {
        content: ''; position: absolute; left: 14px; right: 14px; bottom: 5px;
        height: 3px; border-radius: 3px; background: var(--gold);
    }
    .site-nav .nav-cta { margin-left: 10px; padding: 0 22px; background: var(--green); color: #fff; font-weight: 600; }
    .site-nav .nav-cta:hover,
    .site-nav .nav-cta[aria-current] { background: var(--green-2); color: #fff; }
    .site-nav .nav-cta[aria-current]::after { display: none; }
    .nav-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
        background: #d92d20; color: #fff; font-size: .68rem; font-weight: 700; line-height: 1;
    }
    .nav-toggle { display: none; position: relative; width: 44px; height: 44px; flex-shrink: 0; margin-right: -8px; border: 0; border-radius: 12px; background: transparent; color: var(--green); }
    .nav-toggle span {
        position: absolute; left: 11px; right: 11px; height: 2px; border-radius: 2px;
        background: currentColor; transition: transform .2s, top .2s, opacity .2s;
    }
    .nav-toggle span:nth-child(1) { top: 15px; }
    .nav-toggle span:nth-child(2) { top: 21px; }
    .nav-toggle span:nth-child(3) { top: 27px; }
    .nav-toggle[aria-expanded="true"] span:nth-child(1) { top: 21px; transform: rotate(45deg); }
    .nav-toggle[aria-expanded="true"] span:nth-child(2) { opacity: 0; }
    .nav-toggle[aria-expanded="true"] span:nth-child(3) { top: 21px; transform: rotate(-45deg); }

    @media (max-width: 960px) {
        .nav-toggle { display: block; }
        .site-nav {
            display: none; position: absolute; top: 100%; left: 0; right: 0;
            flex-direction: column; align-items: stretch; gap: 0;
            padding: 8px max(20px, env(safe-area-inset-right)) 20px max(20px, env(safe-area-inset-left));
            background: #fff; box-shadow: 0 1px 0 var(--line), 0 24px 40px rgba(16, 35, 26, .12);
        }
        .site-nav.is-open { display: flex; }
        .site-nav a { min-height: 52px; padding: 0 4px; border-bottom: 1px solid var(--line); border-radius: 0; font-size: 1.02rem; }
        .site-nav a[aria-current]::after { display: none; }
        .site-nav .nav-cta { margin: 16px 0 0; justify-content: center; border-bottom: 0; border-radius: 999px; }
    }

    /* ------------------------------ Hero ------------------------------ */
    .hero {
        position: relative; isolation: isolate; overflow: hidden;
        padding: 112px 0 184px;
        background-color: var(--green); background-image: var(--dots-light); background-size: 22px 22px;
        color: #fff;
    }
    .hero.has-photo { background-image: var(--hero-photo); background-size: cover; background-position: center; }
    /* Dark green on the text side, fading so the photo shows on the right. */
    .hero::before {
        content: ''; position: absolute; inset: 0; z-index: -1;
        background: linear-gradient(100deg, rgba(8, 36, 18, .95) 0%, rgba(8, 36, 18, .82) 45%, rgba(8, 36, 18, .3) 100%);
    }
    .hero:not(.has-photo)::before { background: radial-gradient(55% 70% at 85% 15%, rgba(29, 122, 62, .45), transparent 70%); }
    /* A faint ring for a bit of play in the corner. */
    .hero::after {
        content: ''; position: absolute; top: -110px; right: -110px; z-index: -1;
        width: 320px; height: 320px; border: 44px solid rgba(255, 255, 255, .08); border-radius: 50%;
    }
    .hero-inner { max-width: 660px; }
    .hero .eyebrow { color: var(--gold); }
    .hero h1 {
        margin-top: 16px; font-family: var(--head); font-size: clamp(2.4rem, 1.3rem + 3.8vw, 4.1rem);
        font-weight: 700; line-height: 1.08; letter-spacing: -.02em; text-wrap: balance;
    }
    .hero h1 .hl { color: var(--green-light); }
    .hero-lede { max-width: 34rem; margin-top: 18px; color: rgba(255, 255, 255, .86); font-size: clamp(1rem, .95rem + .3vw, 1.15rem); line-height: 1.65; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
    .hero-latest {
        display: inline-flex; align-items: center; gap: 10px; max-width: 100%; min-height: 44px;
        margin-top: 28px; padding: 6px 16px 6px 6px; border-radius: 999px;
        background: rgba(255, 255, 255, .12); color: #fff; font-size: .9rem; text-decoration: none;
        -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); transition: background-color .15s;
    }
    .hero-latest:hover { background: rgba(255, 255, 255, .2); }
    .hero-latest-tag {
        flex-shrink: 0; padding: 4px 10px; border-radius: 999px; background: var(--gold); color: var(--ink);
        font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    }
    .hero-latest-title { min-width: 0; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hero-latest-date { flex-shrink: 0; opacity: .75; }

    @media (max-width: 760px) {
        .hero { padding: 64px 0 132px; }
        .hero::before { background: linear-gradient(180deg, rgba(8, 36, 18, .9), rgba(8, 36, 18, .8)); }
        .hero:not(.has-photo)::before { background: none; }
        .hero::after { width: 200px; height: 200px; top: -80px; right: -80px; border-width: 30px; }
        .hero-actions .btn { flex: 1 1 200px; }
        .hero-latest-date { display: none; }
    }

    /* Quick links overlapping the bottom of the hero. */
    .quick { position: relative; z-index: 2; margin-top: -104px; }
    .quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; }
    .quick-card {
        position: relative; display: flex; flex-direction: column; gap: 4px; padding: 28px 24px 26px;
        border-radius: var(--radius); background: #fff; box-shadow: var(--shadow-lg);
        color: var(--text); text-decoration: none; overflow: hidden; transition: transform .2s, box-shadow .2s;
    }
    .quick-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gold);
        transform: scaleX(0); transform-origin: left; transition: transform .25s;
    }
    .quick-card:hover { transform: translateY(-6px); }
    .quick-card:hover::before { transform: scaleX(1); }
    .quick-icon {
        display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 12px; border-radius: 14px;
        background: var(--green-soft); color: var(--green); font-size: 1.4rem; transition: background-color .2s, color .2s;
    }
    .quick-card:hover .quick-icon { background: var(--green); color: #fff; }
    .quick-card strong { color: var(--ink); font-family: var(--head); font-size: 1.08rem; font-weight: 600; }
    .quick-card span:last-child { color: var(--muted); font-size: .9rem; line-height: 1.5; }
    @media (max-width: 760px) {
        .quick { margin-top: -88px; }
        .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .quick-card { padding: 18px 16px; }
        .quick-icon { width: 44px; height: 44px; margin-bottom: 8px; font-size: 1.15rem; }
        .quick-card strong { font-size: .98rem; }
        .quick-card span:last-child { font-size: .82rem; }
    }

    /* ---------------------------- Sections ---------------------------- */
    .section { padding: 100px 0; }
    .section-tint { background: var(--tint); }
    .sec-head {
        display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
        gap: 18px 32px; margin-bottom: 44px;
    }
    .sec-head h2,
    .contact-intro h2,
    .cta h2 {
        color: var(--ink); font-family: var(--head); font-size: clamp(1.9rem, 1.35rem + 1.7vw, 2.6rem);
        font-weight: 700; letter-spacing: -.02em; line-height: 1.15;
    }
    .sec-head h2 { margin-top: 10px; }
    .sec-head p { max-width: 36rem; margin-top: 12px; color: var(--muted); }
    .sec-head-center { flex-direction: column; align-items: center; text-align: center; }
    .sec-head-center .eyebrow::after { content: ''; width: 26px; height: 3px; border-radius: 3px; background: var(--gold); }
    .sec-head-center p { margin-left: auto; margin-right: auto; }
    .more-row { display: flex; justify-content: center; margin-top: 40px; }

    .empty {
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        padding: 56px 24px; border-radius: var(--radius); background: #fff; box-shadow: var(--shadow);
        color: var(--muted); text-align: center;
    }
    .section-tint .empty { background: #fff; }
    .empty > i {
        display: grid; place-items: center; width: 60px; height: 60px; margin-bottom: 8px; border-radius: 50%;
        background: var(--green-soft); color: var(--green); font-size: 1.5rem;
    }
    .empty strong { color: var(--ink); font-family: var(--head); font-weight: 600; font-size: 1.05rem; }

    @media (max-width: 760px) {
        .section { padding: 72px 0; }
        .sec-head { margin-bottom: 32px; }
    }

    /* -------------------------- Announcements -------------------------- */
    .an-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 28px; }
    .an-card {
        display: flex; flex-direction: column; min-width: 0; border-radius: var(--radius);
        background: #fff; box-shadow: var(--shadow); overflow: hidden; transition: transform .2s, box-shadow .2s;
    }
    .an-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .an-top { position: relative; aspect-ratio: 16 / 9; background: var(--tint-2); overflow: hidden; }

    /* Posts without photos get a cover in the SG colours, in three variations. */
    .an-cover {
        position: absolute; inset: 0; display: grid; place-items: center;
        background-color: var(--green); color: #fff;
        background-image: var(--dots-light), linear-gradient(135deg, var(--green-3), var(--green));
        background-size: 18px 18px, 100% 100%;
    }
    .an-cover i { font-size: 2.6rem; line-height: 1; opacity: .9; transition: transform .3s; }
    .an-card:hover .an-cover i { transform: scale(1.08); }
    .an-cover-1 { color: var(--green-3); background-image: var(--dots-dark), linear-gradient(135deg, #f3f9f5, var(--green-soft)); }
    .an-cover-2 { color: var(--green-light); background-image: var(--dots-light), linear-gradient(135deg, var(--green-2), var(--green-dark)); }

    /* Photo posts: the first photo fills the cover; tapping it opens the viewer. */
    .an-card .an-media { position: absolute; inset: 0; }
    .an-card .pg { display: block; height: 100%; border-radius: 0; }
    .an-card .pg-tile { display: none; width: 100%; height: 100%; aspect-ratio: auto; }
    .an-card .pg-tile:first-child { display: block; }
    .an-card .pg .pg-tile img { position: absolute; inset: 0; width: 100%; height: 100%; max-height: none; object-fit: cover; }
    .an-card .pg-more { display: none; }
    .an-count {
        position: absolute; top: 12px; right: 12px; z-index: 1; display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px; background: rgba(10, 30, 18, .72); color: #fff;
        font-size: .78rem; font-weight: 600; pointer-events: none;
    }
    /* Date tile on the cover, like an event calendar. */
    .an-date {
        position: absolute; left: 16px; bottom: 16px; z-index: 1; min-width: 54px; padding: 6px 8px 7px;
        display: flex; flex-direction: column; align-items: center; line-height: 1.05;
        border-radius: 12px; background: #fff; box-shadow: 0 6px 16px rgba(0, 0, 0, .16); pointer-events: none;
    }
    .an-date b { color: var(--green); font-family: var(--head); font-size: 1.35rem; font-weight: 700; }
    .an-date span { color: var(--muted); font-size: .66rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }

    /* Photo tiles in the pop-up. One photo keeps its own shape (up to a
       height limit), two sit side by side, three put the first on top,
       four or more make a 2x2 with "+N" on the last. */
    .pg { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; border-radius: 12px; overflow: hidden; }
    .pg-1 { grid-template-columns: 1fr; }
    .pg-tile { position: relative; aspect-ratio: 1 / 1; border: 0; background: var(--tint-2); overflow: hidden; cursor: zoom-in; }
    .pg-3 .pg-tile:first-child { grid-column: 1 / -1; aspect-ratio: 2 / 1; }
    /* Pinned to the tile's edges: a percentage height doesn't resolve inside a <button>. */
    .pg-tile img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
    .pg-1 .pg-tile { aspect-ratio: auto; }
    .pg-1 .pg-tile img { position: static; height: auto; max-height: 26rem; }
    .pg-tile:hover img { transform: scale(1.04); }
    /* The grid clips its corners, so the focus ring is drawn inside the tile. */
    .pg-tile:focus-visible { outline-offset: -3px; }
    .pg-tile:focus-visible::after { content: ''; position: absolute; inset: 3px; border: 2px solid #fff; pointer-events: none; }
    .pg-more {
        position: absolute; inset: 0; display: grid; place-items: center;
        background: rgba(10, 30, 18, .58); color: #fff; font-size: 1.5rem; font-weight: 700;
    }

    .an-text { flex: 1; display: flex; flex-direction: column; min-width: 0; padding: 22px 24px 14px; }
    .an-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; color: var(--faint); font-size: .82rem; }
    /* The cover's date tile shows the date on cards; the pop-up shows it as text. */
    .an-card .an-meta time {
        position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap;
    }
    /* Unread since the last visit: a notification dot on the date tile, not a banner. */
    .an-card.is-unread .an-date::after {
        content: ''; position: absolute; top: -4px; right: -4px; width: 12px; height: 12px;
        border-radius: 50%; background: #d92d20; box-shadow: 0 0 0 2px #fff;
    }
    .chip {
        display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px;
        background: var(--gold); color: var(--ink); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    }
    .an-title {
        margin-bottom: 8px; color: var(--ink); font-family: var(--head); font-size: 1.12rem; font-weight: 600;
        letter-spacing: -.01em; line-height: 1.35; overflow-wrap: anywhere;
    }
    .an-body { color: var(--muted); font-size: .95rem; line-height: 1.65; overflow-wrap: anywhere; }
    .an-body :is(p, ul, ol, blockquote, h1, h2, h3) + :is(p, ul, ol, blockquote, h1, h2, h3) { margin-top: .65em; }
    .an-body :is(ul, ol) { padding-left: 1.3em; }
    .an-body blockquote { padding-left: 14px; border-left: 3px solid var(--green-soft); font-style: italic; }
    .an-body :is(h1, h2, h3) { color: var(--ink); font-size: 1.05em; font-weight: 700; line-height: 1.35; }
    .an-body a { color: var(--green-3); text-underline-offset: 3px; }
    .an-body :is(img, video, iframe) { max-width: 100%; height: auto; }
    .an-body table { display: block; max-width: 100%; overflow-x: auto; }
    .an-body.clamped { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; line-clamp: 3; overflow: hidden; }
    .an-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0 20px; margin-top: auto; padding-top: 6px; }
    .an-attach-name { max-width: 13rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    @media (max-width: 1024px) { .an-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; } }
    @media (max-width: 640px) { .an-grid { grid-template-columns: minmax(0, 1fr); gap: 20px; } }

    /* ---------------------------- Stats band ---------------------------- */
    .stats {
        position: relative; isolation: isolate; overflow: hidden; padding: 64px 0;
        background-color: var(--green); background-image: var(--dots-light), linear-gradient(120deg, var(--green-2), var(--green-dark));
        background-size: 22px 22px, 100% 100%; color: #fff;
    }
    .stats::after {
        content: ''; position: absolute; left: -90px; bottom: -120px; z-index: -1;
        width: 260px; height: 260px; border: 38px solid rgba(255, 255, 255, .06); border-radius: 50%;
    }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 28px 32px; }
    .stat { display: flex; align-items: center; gap: 16px; }
    .stat > i {
        flex-shrink: 0; display: grid; place-items: center; width: 58px; height: 58px; border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .2); color: var(--green-light); font-size: 1.45rem;
    }
    .stat strong { display: block; color: #fff; font-family: var(--head); font-size: 2.3rem; font-weight: 700; line-height: 1; }
    .stat span { display: block; margin-top: 4px; color: rgba(255, 255, 255, .75); font-size: .9rem; }
    @media (max-width: 640px) {
        .stats { padding: 48px 0; }
        .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px 16px; }
        .stat { flex-direction: column; align-items: flex-start; gap: 10px; }
        .stat > i { width: 48px; height: 48px; font-size: 1.2rem; }
        .stat strong { font-size: 1.9rem; }
    }

    /* ---------------------------- Documents ---------------------------- */
    .search { position: relative; width: min(320px, 100%); }
    .search i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
    .search input {
        width: 100%; min-height: 50px; padding: 0 18px 0 46px; border: 1px solid var(--line-2); border-radius: 999px;
        background: #fff; color: var(--ink); font-size: 1rem; box-shadow: 0 2px 8px rgba(16, 35, 26, .04);
        transition: border-color .15s, box-shadow .15s;
    }
    .search input::placeholder { color: var(--faint); }
    .search input:focus { outline: none; border-color: var(--green-3); box-shadow: 0 0 0 4px rgba(29, 122, 62, .14); }

    .filters { display: flex; gap: 8px; margin: -16px 0 24px; padding-bottom: 2px; overflow-x: auto; scrollbar-width: none; }
    .filters::-webkit-scrollbar { display: none; }
    .filter {
        flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 16px;
        border: 1px solid var(--line-2); border-radius: 999px; background: #fff; color: var(--ink);
        font-size: .88rem; font-weight: 500; transition: background-color .15s, border-color .15s, color .15s;
    }
    .filter:hover { border-color: var(--green-3); }
    .filter span { color: var(--faint); font-size: .8rem; }
    .filter[aria-pressed="true"] { background: var(--green); border-color: var(--green); color: #fff; }
    .filter[aria-pressed="true"] span { color: rgba(255, 255, 255, .75); }

    .doc-list { list-style: none; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .doc-btn {
        width: 100%; height: 100%; display: flex; align-items: center; gap: 16px; padding: 16px 18px;
        border: 1px solid var(--line); border-radius: 14px; background: #fff; text-align: left;
        transition: border-color .15s, box-shadow .2s, transform .2s;
    }
    .doc-btn:hover { border-color: transparent; box-shadow: var(--shadow-lg); transform: translateY(-3px); }
    /* Colour per file type so a PDF or a Word file is recognised at a glance. */
    .doc-type {
        flex-shrink: 0; width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center;
        font-size: .62rem; font-weight: 800; letter-spacing: .05em;
    }
    .t-pdf { background: #fde8e7; color: #b42318; }
    .t-word { background: #e0e9ff; color: #1d4ed8; }
    .t-sheet { background: #dcfae6; color: #05603a; }
    .t-slides { background: #fef0c7; color: #93370d; }
    .t-image { background: #f0eaff; color: #6941c6; }
    .t-other { background: var(--tint-2); color: var(--muted); }
    .doc-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
    .doc-name {
        color: var(--ink); font-weight: 600; line-height: 1.4; overflow-wrap: anywhere;
        display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden;
    }
    .doc-meta { color: var(--faint); font-size: .82rem; }
    .doc-meta span { white-space: nowrap; }
    .doc-go {
        flex-shrink: 0; display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%;
        background: var(--tint); color: var(--green); transition: background-color .15s, color .15s;
    }
    .doc-btn:hover .doc-go { background: var(--green); color: #fff; }
    @media (max-width: 760px) {
        .doc-list { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .sec-head .search { width: 100%; }
        /* Chip row scrolls sideways edge to edge, so a cut-off chip signals "swipe for more". */
        .filters { margin: -8px -20px 20px; padding: 0 20px 2px; }
    }

    /* ----------------------------- Officers ----------------------------- */
    .tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 32px; }
    .tab {
        display: inline-flex; align-items: center; gap: 8px; min-height: 46px; padding: 0 22px;
        border: 1px solid var(--line-2); border-radius: 999px; background: #fff; color: var(--ink);
        font-size: .93rem; font-weight: 600; transition: background-color .15s, color .15s, border-color .15s;
    }
    .tab:hover { border-color: var(--green-3); color: var(--green-3); }
    .tab[aria-selected="true"] { background: var(--green); border-color: var(--green); color: #fff; }
    .tab-count {
        display: inline-grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px;
        background: var(--tint); color: var(--muted); font-size: .74rem; font-weight: 700;
    }
    .tab[aria-selected="true"] .tab-count { background: rgba(255, 255, 255, .2); color: #fff; }
    .branch:focus-visible { outline-offset: 8px; border-radius: var(--radius); }
    .branch-about { max-width: 36rem; margin: 0 auto 40px; color: var(--muted); text-align: center; }
    .branch-about strong { color: var(--ink); }

    /* Tiers read top-down like an org chart: the head, their deputies, then everyone else. */
    .tier { display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; }
    .tier + .tier { margin-top: 28px; }
    .person {
        width: 210px; padding: 28px 16px 22px; border-radius: var(--radius); background: #fff;
        box-shadow: var(--shadow); text-align: center; transition: transform .2s, box-shadow .2s;
    }
    .person:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .avatar {
        position: relative; display: grid; place-items: center; width: 92px; height: 92px; margin: 0 auto 16px;
        border-radius: 50%; overflow: hidden; color: #fff; font-family: var(--head); font-size: 1.6rem; font-weight: 600;
        background: linear-gradient(135deg, var(--green-3), var(--green));
        box-shadow: 0 0 0 4px #fff, 0 0 0 6px var(--tint-2);
    }
    .avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .person figcaption strong { display: block; color: var(--ink); font-family: var(--head); font-size: 1rem; font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; }
    .person figcaption span {
        display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 999px;
        background: var(--tint); color: var(--green-3); font-size: .78rem; font-weight: 600; line-height: 1.4;
    }
    /* The head of the branch: a larger card with a gold ring. */
    .tier-0 .person { width: 280px; padding: 34px 20px 26px; border-top: 4px solid var(--green-3); }
    .tier-0 .avatar { width: 128px; height: 128px; font-size: 2.2rem; box-shadow: 0 0 0 4px #fff, 0 0 0 7px var(--gold); }
    .tier-0 figcaption strong { font-size: 1.2rem; }
    .tier-0 figcaption span { background: var(--green); color: #fff; }
    .tier-1 .avatar { width: 104px; height: 104px; font-size: 1.8rem; }

    @media (max-width: 600px) {
        .tier { gap: 12px; }
        .tier + .tier { margin-top: 12px; }
        .person { width: calc(50% - 6px); max-width: 220px; padding: 22px 10px 18px; }
        .avatar { width: 76px; height: 76px; font-size: 1.35rem; }
        .tier-1 .avatar { width: 84px; height: 84px; }
        .tier-0 .person { width: 100%; max-width: 300px; }
        .tier-0 .avatar { width: 112px; height: 112px; }
        .tabs { gap: 6px; margin-bottom: 24px; }
        .tab { flex: 1 1 0; justify-content: center; gap: 6px; padding: 0 10px; font-size: .86rem; }
        .branch-about { margin-bottom: 28px; }
    }

    /* ------------------------- Call to action card ------------------------- */
    .cta { padding: 0 0 100px; }
    .section-tint + .cta { padding-top: 100px; }
    .cta-card {
        position: relative; isolation: isolate; overflow: hidden;
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px 40px;
        padding: 48px 56px; border-radius: 24px; color: #fff;
        background-color: var(--green); background-image: var(--dots-light), linear-gradient(120deg, var(--green-3), var(--green));
        background-size: 20px 20px, 100% 100%;
    }
    .cta-card::after {
        content: ''; position: absolute; right: -70px; top: -110px; z-index: -1;
        width: 260px; height: 260px; border: 38px solid rgba(255, 255, 255, .07); border-radius: 50%;
    }
    .cta h2 { color: #fff; font-size: clamp(1.5rem, 1.2rem + 1.1vw, 2.1rem); }
    .cta p { max-width: 36rem; margin-top: 8px; color: rgba(255, 255, 255, .8); }
    @media (max-width: 760px) {
        .cta { padding-bottom: 72px; }
        .section-tint + .cta { padding-top: 72px; }
    }
    @media (max-width: 600px) {
        .cta-card { padding: 32px 24px; }
        .cta .btn { width: 100%; }
    }

    /* ------------------------------ About ------------------------------ */
    .about-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 28px; }
    .about-card {
        position: relative; padding: 40px 36px; border-radius: var(--radius); background: #fff;
        box-shadow: var(--shadow); overflow: hidden;
    }
    .about-icon {
        display: grid; place-items: center; width: 58px; height: 58px; border-radius: 16px;
        background: var(--green-soft); color: var(--green); font-size: 1.5rem;
    }
    .about-card .label { margin: 20px 0 10px; color: var(--ink); font-family: var(--head); font-size: 1.35rem; font-weight: 700; }
    .about-text { color: var(--text); font-size: 1.04rem; line-height: 1.75; white-space: pre-line; text-wrap: pretty; }
    .about-card img,
    .hymn-body img { margin: 0 auto; border-radius: 12px; }

    .hymn { margin-top: 28px; border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
    .hymn summary {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        min-height: 72px; padding: 0 28px; list-style: none; cursor: pointer;
        color: var(--ink); font-family: var(--head); font-weight: 600; font-size: 1.05rem;
    }
    .hymn summary::-webkit-details-marker { display: none; }
    .hymn-title { display: inline-flex; align-items: center; gap: 12px; }
    .hymn-title i {
        display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%;
        background: var(--green-soft); color: var(--green); font-size: 1.05rem;
    }
    .hymn-hint { display: inline-flex; align-items: center; gap: 6px; color: var(--green-3); font-family: 'Inter', sans-serif; font-size: .88rem; font-weight: 600; }
    .hymn-hint i { transition: transform .2s; }
    .hymn[open] .hymn-hint i { transform: rotate(180deg); }
    .hymn .when-open,
    .hymn[open] .when-closed { display: none; }
    .hymn[open] .when-open { display: inline; }
    .hymn-body { padding: 4px 28px 32px; }
    .hymn-body p { color: var(--text); line-height: 1.9; text-align: center; white-space: pre-line; }

    @media (max-width: 700px) {
        .about-card { padding: 28px 24px; }
        .hymn summary { padding: 0 20px; }
        .hymn-body { padding: 4px 20px 24px; }
    }

    /* ----------------------------- Contact ----------------------------- */
    .contact {
        display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        border-radius: 24px; background: #fff; box-shadow: var(--shadow-lg); overflow: hidden;
    }
    .contact-intro {
        position: relative; isolation: isolate; overflow: hidden; padding: 52px 44px;
        background-color: var(--green); background-image: var(--dots-light), linear-gradient(160deg, var(--green-2), var(--green-dark));
        background-size: 20px 20px, 100% 100%; color: rgba(255, 255, 255, .85);
    }
    .contact-intro::after {
        content: ''; position: absolute; right: -90px; bottom: -90px; z-index: -1;
        width: 240px; height: 240px; border: 36px solid rgba(255, 255, 255, .07); border-radius: 50%;
    }
    .contact-intro .eyebrow { color: var(--gold); }
    .contact-intro h2 { margin-top: 10px; color: #fff; }
    .contact-intro > p { margin-top: 14px; max-width: 26rem; }
    .contact-points { list-style: none; display: grid; gap: 16px; margin-top: 32px; }
    .contact-points li { display: flex; align-items: center; gap: 14px; font-size: .94rem; }
    .contact-points i {
        flex-shrink: 0; display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px;
        background: rgba(255, 255, 255, .1); color: var(--green-light); font-size: 1.1rem;
    }
    .contact-points strong { color: #fff; }
    .contact-points a { color: #fff; font-weight: 600; text-underline-offset: 3px; }

    .contact-form { display: grid; gap: 20px; padding: 52px 44px; }
    .field-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .field label { display: block; margin-bottom: 6px; color: var(--ink); font-size: .86rem; font-weight: 600; }
    .field input,
    .field textarea {
        width: 100%; min-height: 50px; padding: 12px 16px; border: 1px solid var(--line-2); border-radius: 12px;
        background: var(--tint); color: var(--ink); font-size: 1rem; transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .field textarea { min-height: 150px; line-height: 1.6; resize: vertical; }
    .field input:focus,
    .field textarea:focus { outline: none; background: #fff; border-color: var(--green-3); box-shadow: 0 0 0 4px rgba(29, 122, 62, .14); }
    .field [aria-invalid="true"] { border-color: var(--danger); }
    .field-error { display: block; margin-top: 6px; color: var(--danger); font-size: .82rem; }
    .form-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 20px; }
    .form-foot small { color: var(--faint); font-size: .82rem; }
    .notice { display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px; border-radius: 12px; font-size: .92rem; font-weight: 500; }
    .notice i { margin-top: 2px; }
    .notice:focus { outline: none; }
    .notice-ok { background: #dcfae6; color: #05603a; }
    .notice-err { background: #fde8e7; color: var(--danger); }

    @media (max-width: 900px) {
        .contact { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 560px) {
        .contact { border-radius: 20px; }
        .contact-intro,
        .contact-form { padding: 32px 22px; }
        .field-row { grid-template-columns: minmax(0, 1fr); }
        .form-foot .btn { width: 100%; }
    }

    /* ----------------------------- Footer ----------------------------- */
    .site-footer { background: var(--green-dark); color: rgba(255, 255, 255, .7); }
    .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1.2fr; gap: 40px 56px; padding-top: 72px; padding-bottom: 52px; }
    .footer-brand { display: flex; align-items: center; gap: 14px; }
    .footer-brand img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: #fff; }
    .footer-brand strong { display: block; color: #fff; font-family: var(--head); font-weight: 600; }
    .footer-brand span { color: var(--green-light); font-size: .72rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }
    .footer-about p { max-width: 24rem; margin-top: 18px; font-size: .92rem; line-height: 1.7; }
    .site-footer h3 {
        position: relative; margin-bottom: 14px; padding-bottom: 12px;
        color: #fff; font-family: var(--head); font-size: 1rem; font-weight: 600;
    }
    .site-footer h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 30px; height: 3px; border-radius: 3px; background: var(--gold); }
    .footer-links { display: flex; flex-direction: column; align-items: flex-start; }
    .footer-links a {
        display: inline-flex; align-items: center; min-height: 36px; font-size: .92rem; text-decoration: none; transition: color .15s, transform .15s;
    }
    .footer-links a:hover { color: #fff; transform: translateX(3px); }
    .footer-contact ul { list-style: none; display: grid; gap: 12px; }
    .footer-contact li { display: flex; align-items: flex-start; gap: 10px; font-size: .92rem; }
    .footer-contact li i { margin-top: 2px; color: var(--green-light); }
    .footer-contact a { text-decoration: none; }
    .footer-contact a:hover { color: #fff; }
    .footer-app {
        display: inline-flex; align-items: center; gap: 8px; min-height: 40px; margin-top: 18px; padding: 0 18px;
        border: 1px solid rgba(255, 255, 255, .25); border-radius: 999px; background: none;
        color: #fff; font-size: .88rem; font-weight: 500; transition: border-color .15s, color .15s;
    }
    .footer-app:hover { border-color: #fff; color: #fff; }
    .footer-bottom {
        padding: 20px 0 max(20px, env(safe-area-inset-bottom));
        border-top: 1px solid rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .5); font-size: .84rem;
    }
    @media (max-width: 860px) {
        .footer-grid { grid-template-columns: 1fr 1fr; padding-top: 56px; }
        .footer-about { grid-column: 1 / -1; }
    }
    @media (max-width: 520px) {
        .footer-grid { grid-template-columns: minmax(0, 1fr); gap: 32px; }
    }

    /* ----------------------------- Dialogs ----------------------------- */
    .dlg {
        width: min(680px, calc(100vw - 24px)); max-width: none; max-height: min(88vh, 900px);
        margin: auto; padding: 0; border: 0; border-radius: 22px;
        background: #fff; color: var(--text); box-shadow: 0 30px 80px rgba(0, 0, 0, .3); overflow: hidden;
    }
    .dlg::backdrop { background: rgba(8, 30, 16, .55); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
    .dlg[open] { display: flex; flex-direction: column; animation: dlg-in .2s ease-out; }
    @keyframes dlg-in { from { opacity: 0; transform: translateY(12px) scale(.98); } }
    .dlg-close {
        flex-shrink: 0; width: 42px; height: 42px; border: 0; border-radius: 50%;
        display: grid; place-items: center; background: var(--tint); color: var(--ink); font-size: .95rem;
        transition: background-color .15s, color .15s;
    }
    .dlg-close:hover { background: var(--green-soft); color: var(--green); }
    .dlg-close-float { position: absolute; top: 14px; right: 14px; z-index: 3; }

    .dlg-scroll { overflow-y: auto; padding: 44px 44px 32px; overscroll-behavior: contain; }
    .an-dlg .an-media { margin-bottom: 24px; }
    .an-dlg .pg { max-width: 460px; margin: 0 auto; }
    .an-dlg .an-title { margin-bottom: 12px; padding-right: 36px; font-size: 1.6rem; font-weight: 700; line-height: 1.25; }
    .an-dlg .an-body { color: var(--text); font-size: 1.02rem; }
    .an-dlg .an-actions { margin-top: 16px; }

    .file-dlg { width: min(960px, calc(100vw - 24px)); height: min(88vh, 880px); max-height: none; }
    .dlg-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 12px 12px 24px; border-bottom: 1px solid var(--line); }
    .dlg-head h3 { min-width: 0; color: var(--ink); font-family: var(--head); font-size: 1rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dlg-body { flex: 1; min-height: 0; overflow: auto; padding: 16px; background: var(--tint); }
    .pv-pane { height: 100%; }
    .pv-center { display: grid; place-items: center; }
    .pv-frame { display: block; width: 100%; height: 100%; min-height: 50vh; border: 0; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px var(--line); }
    .pv-image { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px; }
    .pv-text {
        min-height: 100%; padding: 16px; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px var(--line);
        font-size: .85rem; white-space: pre-wrap; overflow-wrap: anywhere;
    }
    .pv-state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; min-height: 40vh; color: var(--muted); text-align: center; }
    .pv-state > i { font-size: 2.4rem; color: var(--green-3); opacity: .6; }
    .pv-state.is-error > i { color: #d92d20; opacity: 1; }
    .dlg-foot { display: flex; justify-content: flex-end; gap: 12px; padding: 14px 20px max(14px, env(safe-area-inset-bottom)); border-top: 1px solid var(--line); }
    .spinner { width: 32px; height: 32px; border: 3px solid var(--tint-2); border-top-color: var(--green-3); border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Photo viewer: full screen, the whole picture uncropped. Arrows, swipe
       or the arrow keys move between photos; Esc, the X or a tap beside the
       photo closes it. */
    .lb {
        width: 100vw; max-width: none; height: 100vh; height: 100dvh; max-height: none;
        margin: 0; padding: 0; border: 0; background: rgba(6, 18, 10, .96); color: #fff; overflow: hidden;
    }
    .lb::backdrop { background: rgba(6, 18, 10, .96); }
    .lb[open] { display: grid; grid-template-rows: auto minmax(0, 1fr); animation: lb-in .18s ease-out; }
    @keyframes lb-in { from { opacity: 0; } }
    .lb-bar {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: max(12px, env(safe-area-inset-top)) 12px 12px 20px;
    }
    .lb-count { font-size: .9rem; font-weight: 600; font-variant-numeric: tabular-nums; opacity: .85; }
    .lb-stage { position: relative; display: grid; place-items: center; min-height: 0; touch-action: pan-y; }
    .lb-img {
        max-width: calc(100vw - 24px); max-height: calc(100vh - 96px); max-height: calc(100dvh - 96px);
        object-fit: contain; border-radius: 8px; user-select: none; -webkit-user-drag: none; transition: opacity .15s;
    }
    .lb-stage .spinner {
        position: absolute; top: 50%; left: 50%; margin: -16px 0 0 -16px; display: none;
        border-color: rgba(255, 255, 255, .25); border-top-color: #fff;
    }
    .lb-stage.is-loading .spinner { display: block; }
    .lb-stage.is-loading .lb-img { opacity: .3; }
    .lb-btn {
        flex-shrink: 0; width: 44px; height: 44px; margin-left: auto; border: 0; border-radius: 50%;
        display: grid; place-items: center; background: rgba(255, 255, 255, .14); color: #fff; font-size: 1.05rem;
        transition: background-color .15s, color .15s;
    }
    .lb-btn:hover { background: rgba(255, 255, 255, .28); color: #fff; }
    .lb-btn:focus-visible { outline-color: #fff; }
    .lb-prev, .lb-next { position: absolute; top: 50%; margin: -22px 0 0; }
    .lb-prev { left: 12px; }
    .lb-next { right: 12px; }
    .lb.is-single :is(.lb-prev, .lb-next, .lb-count) { display: none; }
    @media (min-width: 761px) {
        /* Room beside the photo, so the arrows never sit on top of it. */
        .lb-img { max-width: calc(100vw - 152px); }
        .lb-prev { left: 20px; }
        .lb-next { right: 20px; }
    }

    /* Phones: announcements open as a bottom sheet, documents full screen. */
    @media (max-width: 700px) {
        .an-dlg { width: 100%; max-height: 92vh; max-height: 92svh; margin: auto 0 0; border-radius: 22px 22px 0 0; }
        .dlg-scroll { padding: 32px 20px max(24px, env(safe-area-inset-bottom)); }
        /* Full-width photos start below the floating close button. */
        .an-dlg .an-media { margin-top: 32px; }
        .an-dlg .an-title { font-size: 1.35rem; }
        .file-dlg { width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
        .file-dlg .dlg-head { padding-top: max(12px, env(safe-area-inset-top)); }
        .dlg-body { padding: 8px; }
        .dlg-foot .btn { flex: 1; }
    }

    /* ------------------------- Get the app (optional) ------------------------- */
    .nav-app {
        display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 48px; margin-top: 10px; padding: 0 16px;
        border: 1px solid var(--line-2); border-radius: 999px; background: #fff; color: var(--green);
        font-size: 1rem; font-weight: 600;
    }
    .nav-app:hover { background: var(--tint); }
    /* On a computer the header stays as it was; the footer link is enough there. */
    @media (min-width: 961px) { .nav-app { display: none; } }

    .install-dlg { width: min(440px, calc(100vw - 24px)); }
    .install-dlg .dlg-scroll { padding: 40px 32px 28px; text-align: center; }
    .install-icon { width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 18px; box-shadow: 0 6px 18px rgba(15, 30, 20, .18); }
    .install-title { color: var(--ink); font-family: var(--head); font-size: 1.4rem; font-weight: 700; letter-spacing: -.01em; line-height: 1.25; }
    .install-lede { margin-top: 8px; color: var(--muted); font-size: .95rem; text-wrap: pretty; }
    .install-note {
        display: flex; align-items: flex-start; gap: 10px; margin-top: 18px; padding: 12px 14px;
        border-radius: 12px; background: var(--green-soft); color: var(--green); font-size: .9rem; text-align: left;
    }
    .install-note i { margin-top: 2px; }
    .install-steps { display: grid; gap: 12px; margin-top: 22px; list-style: none; counter-reset: step; text-align: left; }
    .install-steps li { display: flex; align-items: flex-start; gap: 12px; color: var(--text); font-size: .95rem; line-height: 1.5; counter-increment: step; }
    .install-steps li::before {
        content: counter(step); flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
        display: grid; place-items: center; background: var(--green); color: #fff; font-size: .8rem; font-weight: 700;
    }
    .install-steps strong { color: var(--ink); }
    .install-steps .bi { color: var(--green); }
    .install-extra { margin-top: 18px; color: var(--muted); font-size: .85rem; }
    .install-actions { margin-top: 22px; }
    .install-actions .btn { width: 100%; }
    @media (max-width: 700px) {
        /* A bottom sheet on phones, like the announcement pop-up. */
        .install-dlg { width: 100%; max-height: 92vh; max-height: 92svh; margin: auto 0 0; border-radius: 22px 22px 0 0; }
        .install-dlg .dlg-scroll { padding: 40px 20px max(24px, env(safe-area-inset-bottom)); }
    }

    /* Connection notice: offline, or showing the copy saved for offline use. */
    .net-bar {
        position: fixed; left: 50%; bottom: max(16px, calc(env(safe-area-inset-bottom) + 8px)); z-index: 150;
        width: max-content; max-width: calc(100vw - 24px); min-height: 48px; transform: translateX(-50%);
        display: flex; align-items: center; gap: 10px; padding: 10px 18px;
        border-radius: 16px; background: var(--green-dark); color: #fff; font-size: .88rem; font-weight: 500; line-height: 1.4;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    }
    .net-bar.has-action { padding: 6px 6px 6px 16px; }
    .net-bar > i { flex-shrink: 0; color: var(--green-light); }
    .net-bar-btn {
        flex-shrink: 0; min-height: 36px; padding: 0 14px; border: 0; border-radius: 999px;
        background: #fff; color: var(--green); font-size: .85rem; font-weight: 700;
    }
    .net-bar-btn:disabled { opacity: .65; }
    body.has-net-bar .footer-bottom { padding-bottom: calc(max(20px, env(safe-area-inset-bottom)) + 72px); }

    /* ===================================================================
       Installed app (opened from the home screen). html.is-app is set in
       the page head; without it - the website - nothing below applies.
       One section per screen, a fixed top bar and tabs at the bottom.
       =================================================================== */
    html:not(.is-app) .app-only { display: none !important; }

    html.is-app {
        --header-h: 56px;
        --tabbar-h: 64px;
        scroll-behavior: auto;
        scroll-padding-top: calc(var(--header-h) + env(safe-area-inset-top) + 12px);
    }

    /* Top bar: always solid green (iPhone draws its white clock over it);
       the website's menu, hero and footer give way to the tabs. */
    html.is-app .site-header,
    html.is-app .site-header.is-solid {
        position: fixed; left: 0; right: 0;
        height: calc(var(--header-h) + env(safe-area-inset-top));
        padding-top: env(safe-area-inset-top);
        background: var(--green); color: #fff; box-shadow: none;
        -webkit-user-select: none; user-select: none;
    }
    html.is-app .brand-logo { width: 36px; height: 36px; box-shadow: none; }
    html.is-app .brand-text strong { color: #fff; }
    html.is-app .brand-text small { color: rgba(255, 255, 255, .7); }
    html.is-app .topbar,
    html.is-app .site-nav,
    html.is-app .nav-toggle,
    html.is-app .hero,
    html.is-app .quick,
    html.is-app .stats,
    html.is-app .cta,
    html.is-app .site-footer { display: none; }
    .app-bar-btn {
        flex-shrink: 0; width: 44px; height: 44px; margin-right: -8px; border: 0; border-radius: 50%;
        display: grid; place-items: center; background: transparent; color: #fff; font-size: 1.2rem;
        transition: background-color .15s;
    }
    .app-bar-btn:active { background: rgba(255, 255, 255, .16); }
    .app-bar-btn.is-busy i { animation: spin .8s linear infinite; }

    /* Screens: only the chosen section shows. */
    html.is-app main {
        min-height: 100vh; min-height: 100dvh;
        padding-top: calc(var(--header-h) + env(safe-area-inset-top));
        padding-bottom: calc(var(--tabbar-h) + env(safe-area-inset-bottom));
    }
    html.is-app body.has-net-bar main { padding-bottom: calc(var(--tabbar-h) + env(safe-area-inset-bottom) + 72px); }
    html.is-app main > .section { display: none; padding: 24px 0 32px; background: transparent; }
    html.is-app[data-tab="announcements"] #announcements,
    html.is-app[data-tab="documents"] #documents,
    html.is-app[data-tab="officers"] #officers,
    html.is-app[data-tab="about"] #about,
    html.is-app[data-tab="contact"] #contact { display: block; animation: screen-in .18s ease-out; }
    @keyframes screen-in { from { opacity: 0; } }
    html.is-app .sec-head { gap: 14px 24px; margin-bottom: 20px; }
    html.is-app .sec-head-center { align-items: flex-start; text-align: left; }
    html.is-app .sec-head-center .eyebrow::after { display: none; }
    html.is-app .sec-head-center p { margin-left: 0; }
    html.is-app .sec-head h2,
    html.is-app .contact-intro h2 { font-size: 1.6rem; }
    html.is-app .sec-head p { margin-top: 6px; font-size: .95rem; }

    /* Bottom tabs. */
    .tabbar {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 100;
        display: flex; justify-content: center;
        height: calc(var(--tabbar-h) + env(safe-area-inset-bottom));
        padding: 0 env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        background: rgba(255, 255, 255, .96); box-shadow: 0 -1px 0 var(--line), 0 -8px 24px rgba(16, 35, 26, .06);
        -webkit-backdrop-filter: saturate(1.5) blur(14px); backdrop-filter: saturate(1.5) blur(14px);
        -webkit-user-select: none; user-select: none; -webkit-touch-callout: none;
    }
    .tabbar a {
        position: relative; flex: 1 1 0; min-width: 0; max-width: 112px;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
        color: var(--muted); font-size: .7rem; font-weight: 600; letter-spacing: .01em; text-decoration: none;
    }
    .tab-icon {
        display: grid; place-items: center; width: 56px; height: 30px; border-radius: 999px;
        font-size: 1.2rem; line-height: 1; transition: background-color .2s;
    }
    .tabbar .ico-on,
    .tabbar a[aria-current] .ico-off { display: none; }
    .tabbar a[aria-current] .ico-on { display: inline-block; }
    .tabbar a[aria-current] { color: var(--green); }
    .tabbar a[aria-current] .tab-icon { background: var(--green-soft); }
    .tabbar a:focus-visible { outline-offset: -3px; border-radius: 12px; }
    .tab-label { max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tab-badge { position: absolute; top: 6px; left: calc(50% + 6px); }
    html.is-app .net-bar { bottom: calc(var(--tabbar-h) + env(safe-area-inset-bottom) + 10px); }

    /* iPhone draws the status bar over the app: on a full-screen document
       preview, keep it on green so the clock and battery stay readable. */
    @media (max-width: 700px) {
        html.is-app .file-dlg { border-top: env(safe-area-inset-top) solid var(--green); }
        html.is-app .file-dlg .dlg-head { padding-top: 12px; }
    }

    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
    }
</style>

<script>
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const smooth = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    const store = {
        get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
    };

    /* ---------------- Anonymous usage statistics ----------------
       Tells the SG which announcements and documents students use, for the
       Reports page. No name, cookie or device ID is ever sent - only what was
       viewed - and nothing at all if the browser asks not to be tracked.
       Events go out in small batches, so they never slow the page down. */
    const Stats = (() => {
        const ENDPOINT = @json(route('engagement.track'));
        const optedOut = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1';
        const queue = [];
        let timer = null;

        function flush() {
            clearTimeout(timer);
            while (queue.length) {
                const body = JSON.stringify({ events: queue.splice(0, 25) });
                if (! (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, body))) {
                    fetch(ENDPOINT, { method: 'POST', body, keepalive: true }).catch(() => {});
                }
            }
        }

        function track(type, data = {}) {
            if (optedOut) return;
            queue.push({ type, ...data });
            clearTimeout(timer);
            timer = setTimeout(flush, 1500);
        }

        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
        window.addEventListener('pagehide', flush);

        return { track, flush };
    })();

    // One "visit" per page load. first_today lets Reports count each device
    // once a day, first_ever spots first-time visitors - both kept only in
    // this browser's own storage.
    (function recordVisit() {
        const today = new Date().toLocaleDateString('en-CA');
        const lastDay = store.get('sg_visit_day');
        const visitedBefore = store.get('sg_visited') === '1';
        store.set('sg_visit_day', today);
        store.set('sg_visited', '1');

        const params = new URLSearchParams(location.search);
        const via = params.get('via') === 'push' ? 'push' : null;
        if (via) {
            // Drop the marker so a reload or a shared link isn't counted as another notification tap.
            params.delete('via');
            history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : '') + location.hash);
        }
        Stats.track('visit', { first_today: lastDay !== today, first_ever: ! visitedBefore, via });
    })();

    /* ---------------- Header: solid once scrolled, mobile menu ---------------- */
    const header = $('#site-header');
    const nav = $('#site-nav');
    const navToggle = $('#nav-toggle');

    const syncHeader = () => header.classList.toggle('is-solid', window.scrollY > 24);
    syncHeader();
    window.addEventListener('scroll', syncHeader, { passive: true });

    function setMenu(open) {
        nav.classList.toggle('is-open', open);
        header.classList.toggle('menu-open', open);
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    navToggle.addEventListener('click', () => setMenu(! nav.classList.contains('is-open')));
    $$('a', nav).forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('click', e => {
        if (nav.classList.contains('is-open') && ! nav.contains(e.target) && ! navToggle.contains(e.target)) setMenu(false);
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });

    // Highlight the section being read, so people always know where they are.
    if ('IntersectionObserver' in window) {
        const links = $$('a[href^="#"]', nav);
        const byId = new Map(links.map(a => [a.getAttribute('href').slice(1), a]));
        const spy = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (! entry.isIntersecting) return;
                links.forEach(a => a.removeAttribute('aria-current'));
                byId.get(entry.target.id)?.setAttribute('aria-current', 'true');
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        ['top', ...byId.keys()].forEach(id => { const el = document.getElementById(id); if (el) spy.observe(el); });
    }

    /* ---------------- Dialogs ---------------- */
    function openDialog(dialog) {
        if (! dialog.open) dialog.showModal();
        document.documentElement.classList.add('dialog-open');
    }
    $$('dialog').forEach(dialog => {
        let pressedBackdrop = false;
        // Close on a click that starts and ends on the dimmed backdrop (not a text drag that ends there).
        dialog.addEventListener('pointerdown', e => { pressedBackdrop = e.target === dialog; });
        dialog.addEventListener('click', e => { if (pressedBackdrop && e.target === dialog) dialog.close(); });
        // The photo viewer can open over the announcement dialog, so the page
        // only unlocks once the last dialog has closed.
        dialog.addEventListener('close', () => { if (! $('dialog[open]')) document.documentElement.classList.remove('dialog-open'); });
        $$('[data-close]', dialog).forEach(btn => btn.addEventListener('click', () => dialog.close()));
    });

    /* ---------------- Announcements ---------------- */
    const cards = $$('.an-card');
    const anDialog = $('#announcementModal');
    const anDialogBody = $('#anModalBody');

    // Long announcements show an excerpt. "See more" appears only when the
    // text is actually cut off, so no one taps a link that does nothing
    // (photos open full size from their own tiles).
    function measureCards() {
        cards.forEach(card => {
            if (card.hidden) return;
            const body = $('.an-body', card);
            body.classList.add('clamped');
            const cutOff = body.scrollHeight > body.clientHeight + 2;
            if (! cutOff) body.classList.remove('clamped');
            $('.an-see-more', card).hidden = ! cutOff;
        });
    }
    measureCards();
    document.fonts?.ready.then(measureCards);
    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(measureCards, 150); });

    function openAnnouncement(card) {
        markSeen();
        markViewed(card);
        Stats.track('announcement_open', { id: Number(card.dataset.id) });
        const title = $('.an-title', card).textContent.trim();
        const photos = $('.pg', card);
        anDialogBody.replaceChildren();

        if (photos) {
            const media = document.createElement('div');
            media.className = 'an-media';
            media.append(photos.cloneNode(true));
            anDialogBody.append(media);
        }

        const heading = document.createElement('h2');
        heading.className = 'an-title';
        heading.id = 'anModalTitle';
        heading.textContent = title;
        const body = $('.an-body', card).cloneNode(true);
        body.classList.remove('clamped');
        anDialogBody.append($('.an-meta', card).cloneNode(true), heading, body);

        const attach = $('.an-attach', card);
        if (attach) {
            const actions = document.createElement('div');
            actions.className = 'an-actions';
            actions.append(attach.cloneNode(true));
            anDialogBody.append(actions);
        }

        openDialog(anDialog);
        anDialogBody.scrollTop = 0;
    }

    cards.forEach(card => $('.an-see-more', card).addEventListener('click', () => openAnnouncement(card)));

    /* ---------------- Photo viewer ---------------- */
    const viewer = $('#photoViewer');
    const viewerStage = $('#photoViewerStage');
    const viewerImg = $('#photoViewerImg');
    const viewerCount = $('#photoViewerCount');
    let viewerPhotos = [];
    let viewerTitle = '';
    let viewerAt = 0;

    function showPhoto(index) {
        viewerAt = (index + viewerPhotos.length) % viewerPhotos.length;
        viewerStage.classList.add('is-loading');
        viewerImg.src = viewerPhotos[viewerAt];
        viewerImg.alt = 'Photo ' + (viewerAt + 1) + ' of ' + viewerPhotos.length + ' for ' + viewerTitle;
        viewerCount.textContent = (viewerAt + 1) + ' / ' + viewerPhotos.length;
    }
    viewerImg.addEventListener('load', () => viewerStage.classList.remove('is-loading'));
    viewerImg.addEventListener('error', () => viewerStage.classList.remove('is-loading'));

    // One listener for every tile: the grid on a card and its copy inside
    // the "See more" dialog both open the viewer.
    document.addEventListener('click', e => {
        const tile = e.target.closest('.pg-tile');
        if (! tile) return;
        const grid = tile.closest('.pg');
        viewerPhotos = JSON.parse(grid.dataset.photos);
        viewerTitle = grid.dataset.title;
        viewer.classList.toggle('is-single', viewerPhotos.length < 2);

        // Seeing a photo full size counts as opening the announcement, the
        // same as "See more" (from inside that dialog it is already counted).
        const card = tile.closest('.an-card');
        if (card) {
            markSeen();
            markViewed(card);
            Stats.track('announcement_open', { id: Number(card.dataset.id) });
        }

        showPhoto(Number(tile.dataset.index));
        openDialog(viewer);
    });

    $('#photoViewerPrev').addEventListener('click', () => showPhoto(viewerAt - 1));
    $('#photoViewerNext').addEventListener('click', () => showPhoto(viewerAt + 1));
    viewer.addEventListener('keydown', e => {
        if (viewerPhotos.length < 2) return;
        if (e.key === 'ArrowRight') { e.preventDefault(); showPhoto(viewerAt + 1); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); showPhoto(viewerAt - 1); }
    });

    // Swipe sideways to move between photos; a tap beside the photo closes.
    let swipeFrom = null;
    let swiped = false;
    viewerStage.addEventListener('pointerdown', e => { swipeFrom = e.clientX; swiped = false; });
    viewerStage.addEventListener('pointerup', e => {
        if (swipeFrom === null) return;
        const dx = e.clientX - swipeFrom;
        swipeFrom = null;
        if (Math.abs(dx) > 50 && viewerPhotos.length > 1) { swiped = true; showPhoto(viewerAt + (dx < 0 ? 1 : -1)); }
    });
    viewerStage.addEventListener('pointercancel', () => { swipeFrom = null; });
    viewerStage.addEventListener('click', e => { if (! swiped && e.target === viewerStage) viewer.close(); });
    // Drop the picture on close so a large photo stops downloading.
    viewer.addEventListener('close', () => viewerImg.removeAttribute('src'));

    // An announcement counts as seen once it has been at least half on
    // screen for a second - once per device, so Reports can tell how many
    // students each one actually reached.
    const SEEN_KEY = 'sg_seen_announcements';
    let seenIds = [];
    try { seenIds = JSON.parse(store.get(SEEN_KEY) || '[]'); } catch (e) {}

    function markViewed(card) {
        const id = Number(card.dataset.id);
        if (! id || seenIds.includes(id)) return;
        seenIds.push(id);
        store.set(SEEN_KEY, JSON.stringify(seenIds.slice(-300)));
        Stats.track('announcement_view', { id });
    }

    if ('IntersectionObserver' in window) {
        const dwell = new Map();
        const viewObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const card = entry.target;
                clearTimeout(dwell.get(card));
                if (entry.isIntersecting) {
                    dwell.set(card, setTimeout(() => { markViewed(card); viewObserver.unobserve(card); }, 1000));
                }
            });
        }, { threshold: 0.5 });
        cards.filter(card => ! seenIds.includes(Number(card.dataset.id))).forEach(card => viewObserver.observe(card));
    }

    // Attachment links live in cards and in the pop-up; one listener covers both.
    document.addEventListener('click', e => {
        const link = e.target.closest('a.an-attach');
        if (link) {
            Stats.track('attachment_download', { id: Number(link.dataset.anId) });
            Stats.flush();
        }
    });

    // The hero's "Latest" link opens the newest announcement directly.
    const heroLatest = $('#heroLatest');
    if (heroLatest && cards[0]) {
        heroLatest.addEventListener('click', e => { e.preventDefault(); openAnnouncement(cards[0]); });
    }

    const anMore = $('#anMore');
    if (anMore) {
        const extras = cards.filter(card => card.hasAttribute('data-extra'));
        anMore.addEventListener('click', () => {
            const expand = anMore.getAttribute('aria-expanded') !== 'true';
            extras.forEach(card => { card.hidden = ! expand; });
            anMore.setAttribute('aria-expanded', String(expand));
            anMore.textContent = expand ? 'Show fewer' : 'Show all ' + anMore.dataset.total + ' announcements';
            if (expand) measureCards();
            else anMore.scrollIntoView({ block: 'center', behavior: smooth() });
        });
    }

    // Unread since the last visit (stored only in this browser - no account
    // or server tracking). A first-time visitor only sees the past week's
    // posts as new, not the whole history. The dots stay for this visit so
    // people can still tell which ones are new; only the badge clears.
    const STORAGE_KEY = 'sg_announcements_last_seen';
    const badges = $$('[data-unread-badge]'); // the menu's, and the app's News tab
    let stored = null;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    const since = stored === null ? Date.now() / 1000 - 7 * 86400 : (parseInt(stored, 10) || 0);
    let latestTs = 0;
    let unread = 0;

    cards.forEach(card => {
        const ts = parseInt(card.dataset.ts, 10);
        latestTs = Math.max(latestTs, ts);
        if (ts > since) { unread++; card.classList.add('is-unread'); }
    });
    if (unread) {
        badges.forEach(badge => {
            badge.innerHTML = (unread > 9 ? '9+' : unread) + '<span class="sr-only"> new</span>';
            badge.hidden = false;
        });
    }

    function markSeen() {
        if (! latestTs) return;
        try { localStorage.setItem(STORAGE_KEY, String(latestTs)); } catch (e) {}
        badges.forEach(badge => { badge.hidden = true; });
    }

    const anSection = $('#announcements');
    if (anSection && 'IntersectionObserver' in window) {
        const seen = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) { markSeen(); seen.disconnect(); }
        }, { threshold: 0.25 });
        seen.observe(anSection);
    }
    $$('a[href="#announcements"]').forEach(a => a.addEventListener('click', markSeen));

    /* ---------------- Documents: search, type filter, show all ---------------- */
    const docItems = $$('.doc');
    const DOC_LIMIT = {{ $docLimit }};

    if (docItems.length) {
        const search = $('#docSearch');
        const filters = $$('.filter');
        const empty = $('#docEmpty');
        const status = $('#docStatus');
        const more = $('#docMore');
        let family = 'all';
        let expanded = false;

        function applyDocs() {
            const words = (search?.value || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
            const filtering = words.length > 0 || family !== 'all';
            let matches = 0;

            docItems.forEach((item, i) => {
                const hit = (family === 'all' || item.dataset.family === family)
                    && words.every(word => item.dataset.search.includes(word));
                if (hit) matches++;
                // While searching or filtering every match shows; otherwise only the newest few until "Show all".
                item.hidden = ! (hit && (filtering || expanded || i < DOC_LIMIT));
            });

            empty.hidden = matches > 0;
            if (more) more.parentElement.hidden = filtering;
            status.textContent = filtering ? matches + (matches === 1 ? ' document' : ' documents') + ' found' : '';
        }

        search?.addEventListener('input', applyDocs);

        // Once typing pauses, note the search and how many documents it found
        // (whatever the type filter), so the SG can see what students look
        // for and don't find.
        let searchTimer;
        let lastSearch = '';
        search?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const term = search.value.trim().toLowerCase();
                if (term.length < 2 || term === lastSearch) return;
                lastSearch = term;
                const words = term.split(/\s+/);
                const results = docItems.filter(item => words.every(word => item.dataset.search.includes(word))).length;
                Stats.track('search', { term, results });
            }, 1500);
        });
        filters.forEach(btn => btn.addEventListener('click', () => {
            family = btn.dataset.family;
            filters.forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
            applyDocs();
        }));
        $('#docClear').addEventListener('click', () => {
            if (search) search.value = '';
            family = 'all';
            filters.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.family === 'all')));
            applyDocs();
            search?.focus();
        });
        more?.addEventListener('click', () => {
            expanded = ! expanded;
            more.setAttribute('aria-expanded', String(expanded));
            more.textContent = expanded ? 'Show fewer' : 'Show all ' + more.dataset.total + ' documents';
            applyDocs();
            if (! expanded) more.scrollIntoView({ block: 'center', behavior: smooth() });
        });
        $('#docList').addEventListener('click', e => {
            const btn = e.target.closest('.doc-btn');
            if (! btn) return;
            Stats.track('document_preview', { id: Number(btn.dataset.id) });
            openFilePreview(btn.dataset.id, btn.dataset.name);
        });
    }

    /* ---------------- Document preview ---------------- */
    const fileDialog = $('#filePreviewModal');
    const panes = {
        loading: $('#previewLoading'),
        pdf: $('#pdfPreview'),
        image: $('#imagePreview'),
        office: $('#officePreview'),
        text: $('#textPreview'),
        generic: $('#genericPreview'),
        error: $('#previewError'),
    };
    const previewFoot = $('#previewFoot');
    const downloadBtn = $('#downloadBtn');
    const PREVIEW_URL = @json(route('files.previewStudentDashboard', ['id' => '__ID__']));
    let previewSeq = 0;  // ignores answers to previews that were closed or replaced
    let current = null;  // { url, name } of the file being previewed
    let lastArgs = null;

    function showPane(name) {
        Object.entries(panes).forEach(([key, el]) => { el.hidden = key !== name; });
        previewFoot.hidden = ! current || name === 'loading' || name === 'error';
    }

    function openFilePreview(id, name) {
        const seq = ++previewSeq;
        lastArgs = [id, name];
        current = null;
        $('#modalFileName').textContent = name;
        showPane('loading');
        openDialog(fileDialog);

        fetch(PREVIEW_URL.replace('__ID__', encodeURIComponent(id)), { headers: { Accept: 'application/json' } })
            .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
            .then(data => {
                if (seq !== previewSeq) return;
                if (! data.url) throw new Error('No preview URL');

                current = { id, url: data.url, name: data.name || name };
                const ext = (current.name.split('.').pop() || '').toLowerCase();

                if (ext === 'pdf') {
                    $('#pdfFrame').src = data.url;
                    showPane('pdf');
                } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    const img = $('#imageViewer');
                    img.src = data.url;
                    img.alt = current.name;
                    showPane('image');
                } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(ext)) {
                    $('#officeFrame').src = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(data.url);
                    showPane('office');
                } else if (['txt', 'csv', 'json', 'log'].includes(ext)) {
                    const viewer = $('#textViewer');
                    viewer.textContent = 'Loading…';
                    showPane('text');
                    fetch(data.url)
                        .then(res => res.text())
                        .then(text => { if (seq === previewSeq) viewer.textContent = text; })
                        .catch(() => { if (seq === previewSeq) viewer.textContent = 'This file could not be loaded. Try downloading it instead.'; });
                } else {
                    showPane('generic');
                }
            })
            .catch(() => { if (seq === previewSeq) showPane('error'); });
    }

    $('#previewRetry').addEventListener('click', () => { if (lastArgs) openFilePreview(...lastArgs); });

    fileDialog.addEventListener('close', () => {
        previewSeq++;
        current = null;
        $('#pdfFrame').src = 'about:blank';
        $('#officeFrame').src = 'about:blank';
        $('#imageViewer').removeAttribute('src');
    });

    downloadBtn.addEventListener('click', () => {
        const file = current;
        if (! file) return;
        Stats.track('document_download', { id: Number(file.id) });
        const label = $('span', downloadBtn);
        downloadBtn.disabled = true;
        label.textContent = 'Preparing…';

        fetch(file.url)
            .then(res => res.ok ? res.blob() : Promise.reject(new Error('HTTP ' + res.status)))
            .then(blob => {
                const href = URL.createObjectURL(blob);
                const a = Object.assign(document.createElement('a'), { href, download: file.name });
                document.body.append(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(href), 1000);
            })
            // Storage that blocks cross-site downloads: open the signed link instead.
            .catch(() => window.open(file.url, '_blank', 'noopener'))
            .finally(() => { downloadBtn.disabled = false; label.textContent = 'Download'; });
    });

    // A tapped "new document" notification (?doc=ID) opens that document
    // straight away, since it may sit far down the list. The marker is then
    // dropped so a reload or a shared link doesn't open it again.
    (function openNotifiedDocument() {
        const params = new URLSearchParams(location.search);
        const id = params.get('doc');
        if (! id) return;
        params.delete('doc');
        history.replaceState(history.state, '', location.pathname + (params.toString() ? '?' + params : '') + location.hash);

        const btn = $$('.doc-btn').find(b => b.dataset.id === id);
        if (! btn) return; // made private again since the notification went out
        Stats.track('document_preview', { id: Number(id) });
        openFilePreview(id, btn.dataset.name);
    })();

    /* ---------------- Officers: branch tabs (arrow keys move between them) ---------------- */
    const tabs = $$('[role="tab"]');
    function selectTab(tab, focus) {
        tabs.forEach(t => {
            const on = t === tab;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
            document.getElementById(t.getAttribute('aria-controls')).hidden = ! on;
        });
        if (focus) tab.focus();
    }
    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => selectTab(tab, false));
        tab.addEventListener('keydown', e => {
            const next = { ArrowRight: tabs[(i + 1) % tabs.length], ArrowLeft: tabs[(i - 1 + tabs.length) % tabs.length], Home: tabs[0], End: tabs[tabs.length - 1] }[e.key];
            if (next) { e.preventDefault(); selectTab(next, true); }
        });
    });

    /* ---------------- Offline: the saved copy and connection notices ----------------
       With no connection (or one that hangs), public/sw.js answers with the
       copy of this page saved on an earlier visit, marked with
       <meta name="sg-saved-copy">. Say so, and offer the live page as soon as
       it can load. A half-written message survives that reload. */
    const savedCopy = $('meta[name="sg-saved-copy"]');
    const netBar = $('#netBar');
    const netText = $('#netBarText');
    const netBtn = $('#netBarBtn');
    const feedbackForm = $('#feedbackForm');
    const DRAFT_KEY = 'sg_contact_draft';
    const DRAFT_FIELDS = ['name', 'email', 'student_id', 'subject', 'message'];

    const draftField = name => feedbackForm ? feedbackForm.elements[name] : null;
    const hasDraft = () => DRAFT_FIELDS.some(name => draftField(name)?.value.trim());

    function reloadPage() {
        if (hasDraft()) {
            const draft = Object.fromEntries(DRAFT_FIELDS.map(name => [name, draftField(name)?.value || '']));
            try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft)); } catch (e) {}
        }
        location.reload();
    }

    try {
        const draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null');
        sessionStorage.removeItem(DRAFT_KEY);
        // Never over what the server sent back (the fields to fix after a failed send).
        if (draft && ! hasDraft()) DRAFT_FIELDS.forEach(name => { if (draftField(name)) draftField(name).value = draft[name] || ''; });
    } catch (e) {}

    function showNet(text, action) {
        netText.textContent = text;
        netBtn.hidden = ! action;
        netBtn.disabled = false;
        if (action) netBtn.textContent = action;
        netBar.classList.toggle('has-action', !! action);
        netBar.hidden = false;
        document.body.classList.add('has-net-bar');
    }
    function hideNet() {
        netBar.hidden = true;
        document.body.classList.remove('has-net-bar');
    }
    netBtn.addEventListener('click', () => { netBtn.disabled = true; reloadPage(); });

    if (savedCopy) {
        const savedAt = new Date(savedCopy.content);
        const copy = isNaN(savedAt)
            ? 'Showing a saved copy.'
            : 'Showing the copy saved ' + savedAt.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + '.';
        showNet((navigator.onLine === false ? 'You’re offline. ' : 'Slow connection. ') + copy, 'Try again');
        window.addEventListener('offline', () => showNet('You’re offline. ' + copy, 'Try again'));
        window.addEventListener('online', () => showNet('You’re back online.', 'Refresh'));
        // The live page arrived after all - the connection was only slow.
        navigator.serviceWorker?.addEventListener('message', e => {
            if (e.data && e.data.type === 'page-updated') showNet('The latest version is ready.', 'Refresh');
        });

        // This copy's form token may be out of date, so sending first reloads
        // the live page (the message is kept), and offline it waits.
        feedbackForm?.addEventListener('submit', e => {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (navigator.onLine === false) showNet('You’re offline. Your message is kept here; send it once you’re back online.', null);
            else reloadPage();
        });
    } else {
        window.addEventListener('offline', () => showNet('You’re offline. New posts and documents will load once you reconnect.', null));
        window.addEventListener('online', hideNet);
    }

    /* ---------------- Installed app: one screen per section ----------------
       Only when opened from the home screen (html.is-app, set in the page
       head) - the website itself is unchanged. The bottom tabs switch
       screens, each screen keeps its own scroll position, and Android's back
       button returns to News before it closes the app. */
    const isApp = document.documentElement.classList.contains('is-app');

    if (isApp) {
        const appTabs = $$('#tabbar a');
        const screens = appTabs.map(a => a.dataset.tab);
        const HOME = screens[0];
        const scrollPos = {};
        let screen = null;
        let awayFromHome = !! (history.state && history.state.sgScreen);

        function show(name) {
            if (! screens.includes(name)) name = HOME;
            if (screen) scrollPos[screen] = window.scrollY;
            screen = name;
            document.documentElement.setAttribute('data-tab', name);
            appTabs.forEach(a => {
                if (a.dataset.tab === name) a.setAttribute('aria-current', 'page');
                else a.removeAttribute('aria-current');
            });
            // "See more" can only be measured while the cards are on screen.
            if (name === 'announcements') measureCards();
            window.scrollTo(0, scrollPos[name] || 0);
        }

        function go(name) {
            if (! screens.includes(name) || name === screen) return;
            if (name === HOME && awayFromHome) { history.back(); return; } // popstate shows it
            if (screen === HOME && ! awayFromHome) {
                history.pushState({ sgScreen: true }, '', '#' + name);
                awayFromHome = true;
            } else {
                history.replaceState(history.state, '', '#' + name);
            }
            show(name);
        }

        window.addEventListener('popstate', () => {
            awayFromHome = !! (history.state && history.state.sgScreen);
            show(location.hash.slice(1));
        });

        appTabs.forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            // Tapping the open tab again goes back to its top, as in most apps.
            if (a.dataset.tab === screen) window.scrollTo({ top: 0, behavior: smooth() });
            else go(a.dataset.tab);
        }));

        // Links to another section of the page open that screen instead.
        document.addEventListener('click', e => {
            const link = e.target.closest('a[href^="#"]');
            if (! link || link.closest('#tabbar')) return;
            const id = link.getAttribute('href').slice(1);
            if (screens.includes(id)) { e.preventDefault(); go(id); }
            else if (id === 'top') { e.preventDefault(); window.scrollTo({ top: 0, behavior: smooth() }); }
        });

        // After sending the contact form the page reloads: back to that screen.
        show($('#contactNotice') ? 'contact' : location.hash.slice(1));
        if (screen !== HOME && location.hash !== '#' + screen) history.replaceState(history.state, '', '#' + screen);

        const refreshBtn = $('#appRefresh');
        refreshBtn.addEventListener('click', () => {
            refreshBtn.disabled = true;
            refreshBtn.classList.add('is-busy');
            reloadPage();
        });

        // Phones keep an app open in the background for days. Coming back after
        // half an hour reloads it, so the news is never stale - unless something
        // is open or a message is being written.
        let hiddenAt = 0;
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') { hiddenAt = Date.now(); return; }
            const away = hiddenAt ? Date.now() - hiddenAt : 0;
            hiddenAt = 0;
            if (away > 30 * 60 * 1000 && navigator.onLine !== false && ! $('dialog[open]') && ! hasDraft()) reloadPage();
        });
    }

    /* ---------------- Get the app (optional) ----------------
       The website stays the main way in. "Get the app" (phone menu and
       footer) appears only where the device can add the page to its home
       screen: Chrome, Edge and Samsung Internet install it directly; other
       browsers get the steps for that device. */
    const installDialog = $('#installDialog');
    const installButtons = $$('[data-install-open]');
    const ua = navigator.userAgent;
    const device = {
        ios: /iPad|iPhone|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1),
        android: /Android/i.test(ua),
        // Browsers inside Facebook, Messenger and the like can't add to the home screen.
        inApp: /FBAN|FBAV|FB_IAB|FBIOS|Instagram|Messenger|Line\/|MicroMessenger|TikTok|musical_ly|Snapchat/i.test(ua),
    };
    device.macSafari = ! device.ios && /Macintosh/.test(ua) && /Version\/[\d.]+.*Safari/.test(ua) && ! /Chrome|Chromium|Edg|OPR|Firefox/.test(ua);
    let installedNow = false;

    function syncInstallButtons() {
        const offer = ! isApp && ! installedNow && !! (window.sgInstallPrompt || device.ios || device.android || device.macSafari);
        installButtons.forEach(btn => { btn.hidden = ! offer; });
    }

    function openInstall(reason) {
        setMenu(false);
        const offer = window.sgInstallPrompt;
        if (offer && reason !== 'push') {
            window.sgInstallPrompt = null; // the browser's own offer can be used once
            try { Promise.resolve(offer.prompt()).catch(() => {}); } catch (e) {}
            offer.userChoice
                .then(choice => { installedNow = choice.outcome === 'accepted'; })
                .catch(() => {})
                .finally(syncInstallButtons);
            return;
        }
        const steps = device.ios ? (device.inApp ? 'ios-inapp' : 'ios')
            : device.android ? (device.inApp ? 'android-inapp' : 'android')
            : device.macSafari ? 'mac' : 'other';
        $$('[data-install-steps]', installDialog).forEach(list => { list.hidden = list.dataset.installSteps !== steps; });
        $('[data-install-note]', installDialog).hidden = reason !== 'push';
        openDialog(installDialog);
    }

    installButtons.forEach(btn => btn.addEventListener('click', () => openInstall('button')));
    document.addEventListener('sg:installable', syncInstallButtons);
    window.addEventListener('appinstalled', () => {
        installedNow = true;
        window.sgInstallPrompt = null;
        syncInstallButtons();
    });
    syncInstallButtons();

    // iPhone/iPad in a browser tab: notifications work only in the home-screen
    // app there (iOS 16.4 and later), so "Notify me" explains how to add it.
    const iosVersion = (() => {
        const m = ua.match(/OS (\d+)_(\d+)/) || ua.match(/Version\/(\d+)\.(\d+)/);
        return m ? Number(m[1]) * 100 + Number(m[2]) : 0;
    })();
    const pushToggle = $('#pushToggle');
    if (pushToggle && @json((bool) config('webpush.vapid.public_key')) && ! isApp && device.ios && iosVersion >= 1604 && ! ('PushManager' in window)) {
        pushToggle.hidden = false;
        pushToggle.title = 'On iPhone and iPad, notifications work in the app. Tap to see how to add it.';
        pushToggle.addEventListener('click', () => openInstall('push'));
    }

    /* ---------------- Contact form ---------------- */
    const form = $('#feedbackForm');
    const sendBtn = $('#sendBtn');
    // Prevent a double send while the first one is still going.
    form.addEventListener('submit', () => {
        sendBtn.disabled = true;
        $('span', sendBtn).textContent = 'Sending…';
    });
    // After a send (or a failed one) the page reloads at the top: bring the
    // result into view, and put the cursor in the first field to fix.
    const notice = $('#contactNotice');
    if (notice) {
        const showResult = () => $('#contact').scrollIntoView({ block: 'start' });
        showResult();
        // Again once images and fonts have settled, in case they pushed the form down.
        window.addEventListener('load', showResult, { once: true });
        const firstInvalid = $('[aria-invalid="true"]', form);
        (firstInvalid || notice).focus({ preventScroll: true });
    }
})();
</script>

<script>
// Service worker (public/sw.js): keeps a copy of this page for offline use,
// makes the page installable as an app, and delivers notifications (a new
// announcement or public document). The opt-in is anonymous: only the browser's push token is
// stored, never a name or email.
(function () {
    if (! ('serviceWorker' in navigator)) return;

    const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));
    const SW_URL = @json(asset('sw.js'));
    const PAGE_URL = @json(route('student.dashboard'));
    const SUBSCRIBE_URL = @json(route('push.subscribe'));
    const UNSUBSCRIBE_URL = @json(route('push.unsubscribe'));
    const btn = document.getElementById('pushToggle');
    const label = document.getElementById('pushToggleLabel');
    const icon = btn?.querySelector('i');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const pushSupported = 'PushManager' in window && 'Notification' in window;

    function toKey(b64) {
        const pad = '='.repeat((4 - (b64.length % 4)) % 4);
        const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, c => c.charCodeAt(0));
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        });
    }

    // Resolves once the registration has a running worker (push needs one).
    function activated(reg) {
        return new Promise((resolve, reject) => {
            if (reg.active) return resolve(reg);
            const worker = reg.installing || reg.waiting;
            if (! worker) return reject(new Error('No service worker'));
            worker.addEventListener('statechange', function check() {
                if (reg.active) { worker.removeEventListener('statechange', check); resolve(reg); }
                else if (worker.state === 'redundant') { worker.removeEventListener('statechange', check); reject(new Error('Service worker failed to install')); }
            });
        });
    }

    // The worker used to cover the whole site ("/"). Move a notification
    // sign-up from that old registration to this page's own (permission is
    // already granted, so no prompt), then remove the old one. The old
    // sign-up is only dropped once the server has accepted the new one;
    // until then it keeps delivering, and the next visit tries again.
    async function moveOldWorker(reg) {
        const rootScope = new URL('./', SW_URL).href;
        const old = (await navigator.serviceWorker.getRegistrations()).find(other => other.scope === rootScope
            && [other.active, other.waiting, other.installing].some(worker => worker && worker.scriptURL === SW_URL));
        if (! old || old === reg) return;

        try {
            const oldSub = await old.pushManager.getSubscription();
            if (oldSub) {
                if (pushSupported && VAPID_PUBLIC_KEY && Notification.permission === 'granted') {
                    const sub = await reg.pushManager.getSubscription()
                        || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(VAPID_PUBLIC_KEY) });
                    if (sub.endpoint === oldSub.endpoint) return;
                    // "moved": not a new opt-in or opt-out for Reports.
                    const res = await post(SUBSCRIBE_URL, { ...sub.toJSON(), moved: true });
                    if (! res.ok) return;
                }
                await post(UNSUBSCRIBE_URL, { endpoint: oldSub.endpoint, moved: true }).catch(() => {});
                await oldSub.unsubscribe();
            }
            await old.unregister();
        } catch (e) {
            // Leave it for now: the old worker still delivers notifications.
        }
    }

    // Scoped to this page alone, so it never handles the officers' pages.
    // Everything below uses this registration directly, never
    // navigator.serviceWorker.ready, which can still point at the old one.
    const ready = navigator.serviceWorker.register(SW_URL, { scope: PAGE_URL })
        .then(activated)
        .then(reg => moveOldWorker(reg).then(() => reg));

    // Save a copy for offline use from the very first visit.
    ready.then(reg => { if (reg.active) reg.active.postMessage({ type: 'save-page' }); }).catch(() => {});

    // No push here (or not set up): the button stays hidden - or, on an
    // iPhone browser tab, explains the app instead (see the main script).
    if (! btn || ! pushSupported || ! VAPID_PUBLIC_KEY) return;

    function render(subscribed) {
        btn.hidden = false;
        btn.classList.toggle('is-on', subscribed);
        btn.setAttribute('aria-pressed', subscribed ? 'true' : 'false');
        btn.disabled = false;
        icon.className = subscribed ? 'bi bi-bell-fill' : 'bi bi-bell';
        if (Notification.permission === 'denied' && !subscribed) {
            label.textContent = 'Notifications blocked';
            btn.disabled = true;
            btn.title = 'Notifications are blocked for this site. Allow them in your browser’s site settings to turn this on.';
        } else {
            label.textContent = subscribed ? 'Notifications on' : 'Notify me';
        }
    }

    ready
        .then(reg => reg.pushManager.getSubscription())
        .then(sub => render(!!sub))
        .catch(() => { /* registration failed: leave the button hidden */ });

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const reg = await ready;
            const existing = await reg.pushManager.getSubscription();

            if (existing) {
                await post(UNSUBSCRIBE_URL, { endpoint: existing.endpoint });
                await existing.unsubscribe();
                render(false);
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') { render(false); return; }

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: toKey(VAPID_PUBLIC_KEY),
            });

            const res = await post(SUBSCRIBE_URL, sub.toJSON());
            if (!res.ok) {
                await sub.unsubscribe(); // don't keep a subscription the server refused
                throw new Error('Server refused the subscription');
            }
            render(true);
        } catch (e) {
            console.error(e);
            alert('Sorry, we couldn’t turn notifications on right now. Please try again later.');
            const sub = await ready.then(r => r.pushManager.getSubscription()).catch(() => null);
            render(!!sub);
        }
    });
})();
</script>
@endsection
