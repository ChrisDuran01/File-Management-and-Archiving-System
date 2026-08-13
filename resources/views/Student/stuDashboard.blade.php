{{-- qsu-transparency-dashboard.blade.php --}}
@extends('Student.home')

@section('content')
<div class="dashboard-container">
    {{-- Site Header / Navbar --}}
    <header class="site-header" id="site-header">
        <div class="site-header-inner">
            <a href="#home" class="site-brand">
                <img src="{{ $siteSettings->logo_path ? asset('storage/'.$siteSettings->logo_path) : asset('images/SG-logo.png') }}" alt="QSU Student Government Logo" class="brand-logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/40x40?text=SG';">
                <span class="brand-name">QSU Student Government</span>
            </a>

            <nav class="site-nav" id="site-nav">
                <a href="#home">Home</a>
                <a href="#vision-mission">Vision &amp; Mission</a>
                <a href="#hymn">Hymn</a>
                <a href="#announcements">Announcements <span id="announcementsBadge" class="nav-badge" style="display:none;"></span></a>
                <a href="#documents">Documents</a>
                <a href="#contact">Contact</a>
            </nav>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    {{-- Hero Masthead --}}
    <div class="hero-section {{ $siteSettings->hero_background_path ? 'has-bg' : '' }}" id="home"
        @if($siteSettings->hero_background_path)
            style="background-image:url('{{ asset('storage/'.$siteSettings->hero_background_path) }}');"
        @endif
    >
        <div class="hero-logo-wrap">
            <div class="sunburst"></div>
            <img src="{{ $siteSettings->logo_path ? asset('storage/'.$siteSettings->logo_path) : asset('images/SG-logo.png') }}" alt="QSU Student Government Logo" class="sg-logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/120x120?text=SG';">
        </div>
        <h1 class="hero-title">QSU-Diffun Student Government</h1>
        <p class="hero-subtitle">Official documents open to all QSU students</p>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4 position-relative" style="z-index: 2; background: #faf9f5;">

        {{-- Vision & Mission --}}
        <div class="doc-section mb-4" id="vision-mission">
            <h2 class="section-title" style="justify-content:center;margin-bottom:1rem;">
                <span class="material-icons-outlined">flag</span>
                Vision &amp; Mission
            </h2>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="info-text-card">
                        @if($siteSettings->vision_image_path)
                            <img src="{{ asset('storage/'.$siteSettings->vision_image_path) }}" alt="Vision" class="info-card-image">
                        @else
                            <h3>Vision</h3>
                            <p>{{ $siteSettings->vision ?: 'Not set yet.' }}</p>
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="info-text-card">
                        @if($siteSettings->mission_image_path)
                            <img src="{{ asset('storage/'.$siteSettings->mission_image_path) }}" alt="Mission" class="info-card-image">
                        @else
                            <h3>Mission</h3>
                            <p>{{ $siteSettings->mission ?: 'Not set yet.' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- QSU Hymn --}}
        <div class="doc-section mb-4" id="hymn">
            <h2 class="section-title" style="justify-content:center;margin-bottom:1rem;">
                <span class="material-icons-outlined">music_note</span>
                QSU Hymn
            </h2>

            <div class="info-text-card" style="max-width:640px;margin:0 auto;text-align:center;">
                @if($siteSettings->hymn_image_path)
                    <img src="{{ asset('storage/'.$siteSettings->hymn_image_path) }}" alt="QSU Hymn" class="info-card-image">
                @else
                    <p style="white-space:pre-line;">{{ $siteSettings->hymn ?: 'Not set yet.' }}</p>
                @endif
            </div>
        </div>

        {{-- Officers Marquee --}}
        @if($officers->count())
        <div class="officers-section mb-4">
            <h2 class="section-title" style="justify-content:center;margin-bottom:1rem;">
                <span class="material-icons-outlined">groups</span>
                Meet Your Student Government
            </h2>

            <div class="marquee-wrap">
                <div class="marquee-track">
                    @foreach($officers as $term)
                        <div class="officer-card">
                            @if($term->user->profile_photo)
                                <img src="{{ asset('storage/'.$term->user->profile_photo) }}" alt="{{ $term->user->name }}" class="officer-photo">
                            @else
                                <div class="officer-photo officer-initial">{{ strtoupper(substr($term->user->name, 0, 1)) }}</div>
                            @endif
                            <div class="officer-name">{{ $term->user->name }}</div>
                            <div class="officer-position">{{ $term->position->position_name ?? '' }}</div>
                        </div>
                    @endforeach
                    @foreach($officers as $term)
                        <div class="officer-card" aria-hidden="true">
                            @if($term->user->profile_photo)
                                <img src="{{ asset('storage/'.$term->user->profile_photo) }}" alt="" class="officer-photo">
                            @else
                                <div class="officer-photo officer-initial">{{ strtoupper(substr($term->user->name, 0, 1)) }}</div>
                            @endif
                            <div class="officer-name">{{ $term->user->name }}</div>
                            <div class="officer-position">{{ $term->position->position_name ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Announcements Section --}}
        <div class="doc-section mb-4" id="announcements">
            <div class="doc-header">
                <div>
                    <h2 class="section-title">
                        <span class="material-icons-outlined">campaign</span>
                        Announcements
                    </h2>
                    <p class="section-subtitle">{{ $announcements->count() }} announcement(s) from the Student Government</p>
                </div>
            </div>

            <div class="row g-2" id="announcementList">
                @forelse($announcements as $announcement)
                <div class="col-12">
                    <div class="modern-doc-card announcement-card" data-ts="{{ $announcement->created_at->timestamp }}" style="cursor:default;display:block;">
                        <h6 class="doc-name" style="white-space:normal;margin-bottom:6px;">
                            {{ $announcement->title }}
                            @if($announcement->created_at->diffInHours(now()) < 72)
                                <span class="new-pill">New</span>
                            @endif
                        </h6>
                        <div class="doc-meta" style="margin-bottom:8px;">
                            <span class="doc-date">{{ $announcement->created_at->format('M d, Y g:i A') }} &middot; {{ $announcement->created_at->diffForHumans() }}</span>
                        </div>
                        <div style="font-size:.9rem;color:#3a3a3a;margin-bottom:8px;">{!! $announcement->body !!}</div>
                        @if($announcement->attachment_path)
                            <a href="{{ route('announcements.download', $announcement->id) }}" class="attachment-link">
                                <span class="material-icons-outlined" style="font-size:16px;">attach_file</span>
                                {{ $announcement->attachment_original_name ?? 'Download attachment' }}
                            </a>
                        @endif
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="empty-modern">
                        <p>No announcements yet</p>
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Documents Section with Search --}}
        <div class="doc-section" id="documents">
            <div class="doc-header">
                <div>
                    <h2 class="section-title">
                        <span class="material-icons-outlined">description</span>
                        Documents accessible for students
                    </h2>
                    <p class="section-subtitle">{{ $totalFiles }} document(s) available</p>
                </div>
                <div class="search-container">
                    <span class="material-icons-outlined">search</span>
                    <input type="text" id="search" class="modern-search" placeholder="Search documents...">
                </div>
            </div>

            {{-- Document Grid --}}
            <div class="row g-3" id="fileContainer">
                @forelse($files as $file)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 file-item">
                    <div class="modern-doc-card" onclick="openFilePreview('{{ $file->id }}', '{{ addslashes($file->filename) }}', '{{ $file->type }}')">
                        <div class="doc-content">
                            <h6 class="doc-name">{{ $file->filename }}</h6>
                            <div class="doc-meta">
                                <span class="doc-tag">{{ strtoupper($file->type) }}</span>
                                <span class="doc-date">{{ $file->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                        <div class="preview-indicator">
                            <span class="material-icons-outlined" style="font-size:18px;">visibility</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="empty-modern">
                        <p>No documents available yet</p>
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Contact Section --}}
        <div class="contact-modern mt-4" id="contact">
            <div class="contact-header">
                <h2 class="section-title">
                    <span class="material-icons-outlined" style="font-size: 24px; line-height: 1;">mail_outline</span>
                    Need help?
                </h2>
                <p>Send us a message — we'll respond within 3-5 business days</p>
            </div>

            <form id="feedbackForm" class="modern-form" method="POST" action="{{ route('send.message') }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="name" id="senderName"
                            placeholder="Full name" class="modern-input" required>
                    </div>

                    <div class="form-group">
                        <input type="email" name="email" id="senderEmail"
                            placeholder="Email address" class="modern-input" required>
                    </div>

                    <div class="form-group">
                        <input type="text" name="student_id" id="studentId"
                            placeholder="Student ID" class="modern-input" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <input type="text" name="subject"
                        placeholder="Subject"
                        class="modern-input" required>
                </div>

                <div class="form-group full-width">
                    <textarea name="message" id="message" rows="4"
                        placeholder="Your message..."
                        class="modern-input modern-textarea"
                        required></textarea>
                </div>

                <button type="submit" class="modern-btn">
                    <span class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">send</span>
                    Send message
                </button>
            </form>

            @if(session('success'))
                <div id="successMsg" class="modern-success mt-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="contact-footer">
                <p>Or email us directly: <strong>sg@qsu.edu.ph</strong></p>
            </div>
        </div>

    </div>

    {{-- Site Footer --}}
    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} QSU Student Government &middot; Quirino State University</p>
    </footer>
</div>

{{-- File Preview Modal --}}
<div id="filePreviewModal" class="file-modal">
    <div class="modal-content glass-modal">
        <div class="modal-header">
            <h3 id="modalFileName">File Preview</h3>
            <button class="modal-close" onclick="closeFilePreview()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Loading State -->
            <div id="previewLoading" style="text-align: center; padding: 40px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 16px; color: #6b7280;">Loading preview...</p>
            </div>

            <!-- PDF Preview -->
            <div id="pdfPreview" style="display: none;">
                <iframe id="pdfFrame" src="" style="width: 100%; height: 500px; border: none; border-radius: 8px;"></iframe>
                <div class="modal-actions">
                    <a id="downloadPdfBtn" href="#" download class="modal-btn download-btn">
                        <span class="material-icons-outlined" style="font-size:18px;">download</span> Download PDF
                    </a>
                </div>
            </div>

            <!-- Image Preview -->
            <div id="imagePreview" style="display: none;">
                <img id="imageViewer" src="" alt="File Preview" style="max-width: 100%; max-height: 500px; border-radius: 8px; object-fit: contain;">
                <div class="modal-actions">
                    <a id="downloadImageBtn" href="#" download class="modal-btn download-btn">
                        <span class="material-icons-outlined" style="font-size:18px;">download</span> Download Image
                    </a>
                </div>
            </div>

            <!-- Document Preview (Word, Excel, PPT) -->
            <div id="officePreview" style="display: none;">
                <div class="office-viewer">
                    <iframe id="officeFrame" src="" style="width: 100%; height: 550px; border: none; border-radius: 8px;"></iframe>
                </div>
                <div class="modal-actions">
                    <a id="downloadOfficeBtn" href="#" download class="modal-btn download-btn">
                        <span class="material-icons-outlined" style="font-size:18px;">download</span> Download File
                    </a>
                </div>
            </div>

            <!-- Text Preview -->
            <div id="textPreview" style="display: none;">
                <div class="text-content">
                    <pre id="textViewer" style="white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow: auto; padding: 16px; background: #f5f5f5; border-radius: 8px;"></pre>
                </div>
                <div class="modal-actions">
                    <a id="downloadTextBtn" href="#" download class="modal-btn download-btn">
                        <span class="material-icons-outlined" style="font-size:18px;">download</span> Download File
                    </a>
                </div>
            </div>

            <!-- Generic Preview -->
            <div id="genericPreview" style="display: none; text-align: center;">
                <div class="generic-file-icon">
                    <span class="material-icons-outlined" style="font-size:64px;color:#9ca3af;">description</span>
                </div>
                <p style="margin-top: 20px;">No preview available for this file type</p>
                <div class="modal-actions">
                    <a id="downloadGenericBtn" href="#" download class="modal-btn download-btn">
                        <span class="material-icons-outlined" style="font-size:18px;">download</span> Download File
                    </a>
                </div>
            </div>

            <!-- Error State -->
            <div id="previewError" style="display: none; text-align: center; padding: 40px;">
                <span class="material-icons-outlined" style="font-size:40px;color:#dc2626;">error_outline</span>
                <p style="margin-top: 16px; color: #dc2626;">Failed to load preview. Please try again later.</p>
                <div class="modal-actions">
                    <button onclick="closeFilePreview()" class="modal-btn download-btn">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        background: #faf9f5;
        min-height: 100vh;
    }

    html {
        scroll-behavior: smooth;
        scroll-padding-top: 76px;
    }

    .dashboard-container {
        background: #faf9f5;
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    #home, #vision-mission, #hymn, #announcements, #documents, #contact {
        scroll-margin-top: 76px;
    }

    /* Site Header / Navbar */
    .site-header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        background: transparent;
        border-bottom: 1px solid transparent;
        transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    }
    .site-header.scrolled {
        background: #ffffff;
        border-bottom: 1px solid #0f3d1f;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .site-header-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 1.25rem;
    }
    .site-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        min-width: 0;
    }
    .brand-logo {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: contain;
        background: #fff;
        flex-shrink: 0;
        border: 1px solid rgba(255,255,255,0.85);
        box-shadow: 0 1px 4px rgba(0,0,0,0.25);
        transition: border-color 0.25s ease, box-shadow 0.25s ease;
    }
    .site-header.scrolled .brand-logo {
        border: 1px solid #0f3d1f;
        box-shadow: none;
    }
    .brand-name {
        font-weight: 700;
        color: #ffffff;
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        letter-spacing: 0.2px;
        text-shadow: 0 1px 3px rgba(0,0,0,0.4);
        transition: color 0.25s ease, text-shadow 0.25s ease;
    }
    .site-header.scrolled .brand-name {
        color: #0f3d1f;
        text-shadow: none;
    }
    .site-nav {
        display: flex;
        align-items: center;
        gap: 1.6rem;
    }
    .site-nav a {
        color: #ffffff;
        font-weight: 500;
        font-size: 1rem;
        text-decoration: none;
        text-shadow: 0 1px 3px rgba(0,0,0,0.4);
        transition: color 0.15s ease, text-shadow 0.25s ease;
    }
    .site-nav a:hover {
        color: #d7f5dd;
    }
    .site-header.scrolled .site-nav a {
        color: #4b5563;
        text-shadow: none;
    }
    .site-header.scrolled .site-nav a:hover {
        color: #0f3d1f;
    }
    .nav-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 17px;
        height: 17px;
        padding: 0 4px;
        border-radius: 9px;
        background: #b3261e;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 700;
        vertical-align: middle;
        margin-left: 2px;
    }
    .nav-toggle {
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 5px;
        width: 32px;
        height: 32px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        flex-shrink: 0;
    }
    .nav-toggle span {
        display: block;
        width: 20px;
        height: 2px;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        transition: transform 0.2s ease, opacity 0.2s ease, background 0.25s ease;
    }
    .site-header.scrolled .nav-toggle span {
        background: #0f3d1f;
        box-shadow: none;
    }
    .nav-toggle.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .nav-toggle.active span:nth-child(2) { opacity: 0; }
    .nav-toggle.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    @media (max-width: 900px) {
        .site-nav {
            position: fixed;
            top: 53px;
            left: 0;
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            background: #ffffff;
            max-height: 0;
            overflow: hidden;
            border-bottom: 1px solid #0f3d1f;
            transition: max-height 0.25s ease;
        }
        .site-nav.open {
            max-height: 320px;
        }
        .site-nav a {
            padding: 12px 1.5rem;
            border-top: 1px solid rgba(0,0,0,0.05);
            color: #4b5563;
            text-shadow: none;
        }
        .site-nav a:hover {
            color: #0f3d1f;
        }
        .nav-toggle {
            display: flex;
        }
    }

    /* Site Footer */
    .site-footer {
        text-align: center;
        padding: 1.4rem 1rem;
        margin-top: 2rem;
        color: #6b7280;
        font-size: 0.78rem;
        border-top: 1px solid #e5e2d8;
        /* Same stacking layer as .container-fluid so it stays above the
           sticky hero (which pins at z-index: 1 for the reveal effect)
           instead of being hidden behind it. */
        position: relative;
        z-index: 2;
        background: #faf9f5;
    }

    /* Hero Masthead */
    .hero-section {
        text-align: center;
        padding: 3.25rem 1.5rem 3rem;
        background: #0f3d1f;
        background-image:
            radial-gradient(circle at 15% 20%, rgba(255,255,255,0.05) 0, transparent 45%),
            radial-gradient(circle at 85% 80%, rgba(255,255,255,0.04) 0, transparent 45%);
        /* Scrolls at a fraction of the page's speed (see the scroll handler
           in the script below) so the content section below visibly gains on
           it and slides over it - a parallax "reveal" instead of the hero
           either scrolling away at normal speed or staying frozen in place. */
        position: relative;
        z-index: 1;
        will-change: transform;
    }
    .hero-section.has-bg {
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 65vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .hero-section.has-bg::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 61, 31, 0.55);
    }
    .hero-section.has-bg > * {
        position: relative;
        z-index: 1;
    }
    .sg-logo {
        width: 450px;
        height: 450px;
        object-fit: contain;
        border-radius: 50%;
        padding: 10px;
        margin-bottom: 1.25rem;
        margin-top: 1.50rem;
        
       
    }
    .hero-title {
        font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;
        font-size: 4rem;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 0.7rem;
        letter-spacing: -0.4px;
    }
    .hero-title::after {
        content: '';
        display: block;
        width: 64px;
        height: 3px;
        background: rgba(255,255,255,0.55);
        margin: 0.75rem auto 0;
    }
    .hero-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 1.02rem;
        margin-top: 1rem;
    }

    /* Vision / Mission / Hymn */
    .info-text-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 1.25rem 1.5rem;
        border: 1px solid #e5e2d8;
        box-shadow: 0 1px 2px rgba(15,61,31,0.04), 0 4px 10px rgba(15,61,31,0.05);
        height: 100%;
    }
    .info-text-card h3 {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f3d1f;
        margin-bottom: 0.6rem;
        text-align: center;
    }
    .info-text-card p {
        font-size: 0.9rem;
        color: #3a3a3a;
        line-height: 1.7;
        margin: 0;
        white-space: pre-line;
    }
    .info-card-image {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
        border-radius: 6px;
    }

    /* Officers Marquee */
    .marquee-wrap {
        overflow: hidden;
        position: relative;
        -webkit-mask-image: linear-gradient(to right, transparent, #000 6%, #000 94%, transparent);
        mask-image: linear-gradient(to right, transparent, #000 6%, #000 94%, transparent);
    }
    .marquee-track {
        display: flex;
        gap: 2.5rem;
        width: max-content;
        animation: marquee-scroll 34s linear infinite;
    }
    .marquee-wrap:hover .marquee-track {
        animation-play-state: paused;
    }
    @keyframes marquee-scroll {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }
    .officer-card {
        flex: 0 0 auto;
        width: 170px;
        text-align: center;
    }
    .officer-photo {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        background: #fff;
        border: 1px solid #0f3d1f;
    }
    .officer-initial {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef2ea;
        color: #0f3d1f;
        font-weight: 700;
        font-size: 2.4rem;
        margin: 0 auto;
    }
    .officer-name {
        font-size: 1rem;
        font-weight: 700;
        color: #0f3d1f;
        margin-top: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .officer-position {
        font-size: 0.82rem;
        color: #6b7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Section headers */
    .doc-section {
        margin-top: 0.5rem;
    }
    .doc-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .section-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: #0f3d1f;
        margin-bottom: 0.15rem;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.2px;
    }
    .section-title .material-icons-outlined {
        font-size: 1.3rem;
    }
    .section-subtitle {
        font-size: 0.8rem;
        color: #6b7280;
    }
    .search-container {
        min-width: 240px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modern-search {
        width: 100%;
        padding: 9px 14px;
        border-radius: 6px;
        border: 1px solid #d6d2c4;
        background: #ffffff;
        font-size: 0.85rem;
        transition: border-color 0.15s ease;
    }
    .modern-search:focus {
        outline: none;
        border-color: #0f3d1f;
    }

    /* Document / Announcement Cards */
    .modern-doc-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 0.9rem 1rem;
        border: 1px solid #e5e2d8;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        box-shadow: 0 1px 2px rgba(15,61,31,0.04), 0 4px 10px rgba(15,61,31,0.05);
    }
    .modern-doc-card:hover {
        border-color: #0f3d1f;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(15,61,31,0.06), 0 10px 20px rgba(15,61,31,0.1);
    }
    .announcement-card {
        position: relative;
    }
    .announcement-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #0f3d1f;
        border-radius: 8px 0 0 8px;
        opacity: 0;
    }
    .announcement-card.is-unread::before {
        opacity: 1;
    }
    .new-pill {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #fff;
        background: #0f3d1f;
        padding: 2px 7px;
        border-radius: 4px;
        vertical-align: middle;
        margin-left: 6px;
    }
    .preview-indicator {
        color: #6b7280;
        flex-shrink: 0;
    }
    .doc-content {
        flex: 1;
        min-width: 0;
    }
    .doc-name {
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f3d1f;
        margin-bottom: 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .doc-meta {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .doc-tag {
        font-size: 0.65rem;
        font-weight: 700;
        background: #ffffff;
        color: #0f3d1f;
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid #0f3d1f;
    }
    .doc-date {
        font-size: 0.7rem;
        color: #9ca3af;
    }
    .attachment-link {
        font-size: 0.82rem;
        color: #0f3d1f;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .attachment-link:hover {
        text-decoration: underline;
    }

    /* Contact Section */
    .contact-modern {
        background: #ffffff;
        border-radius: 8px;
        padding: 1.75rem;
        border: 1px solid #e5e2d8;
        box-shadow: 0 1px 2px rgba(15,61,31,0.04), 0 8px 20px rgba(15,61,31,0.06);
    }
    .contact-header {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .contact-header .section-title {
        justify-content: center;
        margin-bottom: 0.4rem;
    }
    .contact-header p {
        font-size: 0.88rem;
        color: #6b7280;
    }
    .modern-form {
        max-width: 640px;
        margin: 0 auto;
    }
    .form-row {
        display: flex;
        gap: 0.9rem;
        flex-wrap: wrap;
        margin-bottom: 0.9rem;
    }
    .form-group {
        flex: 1;
        min-width: 180px;
    }
    .full-width {
        width: 100%;
        margin-bottom: 0.9rem;
    }
    .modern-input {
        width: 100%;
        padding: 10px 14px;
        border-radius: 6px;
        border: 1px solid #d6d2c4;
        background: #ffffff;
        font-size: 0.85rem;
        font-family: inherit;
        transition: border-color 0.15s ease;
    }
    .modern-input:focus {
        outline: none;
        border-color: #0f3d1f;
    }
    .modern-textarea {
        resize: vertical;
    }
    .modern-btn {
        background: #0f3d1f;
        color: white;
        border: 1px solid #0f3d1f;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.88rem;
        cursor: pointer;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background 0.15s ease;
    }
    .modern-btn:hover {
        background: #145029;
    }
    .modern-success {
        background: #ffffff;
        color: #0f3d1f;
        padding: 0.85rem;
        border-radius: 6px;
        margin-top: 1.2rem;
        text-align: center;
        font-size: 0.85rem;
        font-weight: 500;
        border: 1px solid #0f3d1f;
    }
    .contact-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e2d8;
        font-size: 0.8rem;
        color: #6b7280;
    }
    .contact-footer strong {
        color: #0f3d1f;
    }

    /* Empty State */
    .empty-modern {
        text-align: center;
        padding: 2.5rem;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e5e2d8;
        box-shadow: 0 1px 2px rgba(15,61,31,0.04), 0 4px 10px rgba(15,61,31,0.05);
    }
    .empty-modern p {
        color: #9ca3af;
        font-size: 0.88rem;
    }

    /* Modal Styles */
    .file-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.55);
        z-index: 2000;
        justify-content: center;
        align-items: center;
    }
    .file-modal.active {
        display: flex;
    }
    .glass-modal {
        background: #ffffff;
        border-radius: 8px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 50px -12px rgba(0,0,0,0.3);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e2d8;
        background: #ffffff;
    }
    .modal-header h3 {
        margin: 0;
        color: #0f3d1f;
        font-weight: 600;
        font-size: 1rem;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.6rem;
        cursor: pointer;
        color: #6b7280;
        line-height: 1;
    }
    .modal-close:hover {
        color: #dc2626;
    }
    .modal-body {
        padding: 1.25rem;
        max-height: calc(90vh - 70px);
        overflow-y: auto;
    }
    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.25rem;
    }
    .modal-btn {
        padding: 9px 20px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;
    }
    .download-btn {
        background: #0f3d1f;
        color: white;
        border: 1px solid #0f3d1f;
        cursor: pointer;
    }
    .download-btn:hover {
        background: #145029;
    }
    .generic-file-icon {
        padding: 1.5rem;
    }
    .office-viewer iframe {
        width: 100%;
        min-height: 500px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .glass-modal {
            width: 95%;
        }
        .modal-body {
            padding: 1rem;
        }
        .doc-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .search-container {
            width: 100%;
        }
        .contact-modern {
            padding: 1.25rem;
        }
        .form-row {
            flex-direction: column;
            gap: 0.6rem;
        }
    }

    @media (max-width: 480px) {
        .site-header-inner {
            padding: 0.5rem 1rem;
        }
        .brand-name {
            font-size: 0.82rem;
            max-width: 140px;
        }
        .hero-section {
            padding: 2.25rem 1rem 2rem;
        }
        .hero-section.has-bg {
            min-height: 55vh;
        }
        .hero-title {
            font-size: 1.6rem;
        }
        .sg-logo {
            width: 72px;
            height: 72px;
        }
        .section-title {
            font-size: 1.05rem;
        }
        .contact-modern {
            padding: 1rem;
        }
    }
</style>

<script>
    // Solidify the header once the page is scrolled past the hero
    const siteHeader = document.getElementById('site-header');

    function updateHeaderOnScroll() {
        siteHeader.classList.toggle('scrolled', window.scrollY > 40);
    }

    updateHeaderOnScroll();
    window.addEventListener('scroll', updateHeaderOnScroll, { passive: true });

    // Parallax hero: moves at a fraction of the page's scroll speed so the
    // content section visibly gains on it and slides over it, instead of the
    // hero either scrolling away at full speed or staying frozen in place.
    const heroEl = document.getElementById('home');
    const HERO_SCROLL_SPEED = 0.4; // 0 = fully frozen, 1 = normal scroll speed

    function updateHeroParallax() {
        const maxOffset = heroEl.offsetHeight;
        const offset = Math.min(window.scrollY, maxOffset) * (1 - HERO_SCROLL_SPEED);
        heroEl.style.transform = `translateY(${offset}px)`;
    }

    updateHeroParallax();
    window.addEventListener('scroll', updateHeroParallax, { passive: true });

    // Mobile nav toggle
    const navToggle = document.getElementById('nav-toggle');
    const siteNav = document.getElementById('site-nav');

    navToggle.addEventListener('click', function() {
        const isOpen = siteNav.classList.toggle('open');
        navToggle.classList.toggle('active', isOpen);
        navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    siteNav.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            siteNav.classList.remove('open');
            navToggle.classList.remove('active');
            navToggle.setAttribute('aria-expanded', 'false');
        });
    });

    // Announcement freshness tracking (localStorage only - no account/server tracking needed)
    (function() {
        const STORAGE_KEY = 'sg_announcements_last_seen';
        const cards = document.querySelectorAll('.announcement-card[data-ts]');
        const badge = document.getElementById('announcementsBadge');

        if (!cards.length || !badge) return;

        let lastSeen = 0;
        try {
            lastSeen = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
        } catch (e) {}

        let latestTs = 0;
        let unreadCount = 0;

        cards.forEach(function(card) {
            const ts = parseInt(card.dataset.ts, 10);
            if (ts > latestTs) latestTs = ts;
            if (ts > lastSeen) {
                unreadCount++;
                card.classList.add('is-unread');
            }
        });

        if (unreadCount > 0) {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            badge.style.display = 'inline-flex';
        }

        function markSeen() {
            if (unreadCount === 0) return;
            try {
                localStorage.setItem(STORAGE_KEY, String(latestTs));
            } catch (e) {}
            unreadCount = 0;
            badge.style.display = 'none';
            cards.forEach(function(card) { card.classList.remove('is-unread'); });
        }

        const announcementsSection = document.getElementById('announcements');
        if (announcementsSection && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        markSeen();
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.4 });
            observer.observe(announcementsSection);
        }

        document.querySelector('a[href="#announcements"]').addEventListener('click', markSeen);
    })();

    // Search Filter
    const searchInput = document.getElementById('search');
    const fileItems = document.querySelectorAll('.file-item');

    searchInput.addEventListener('keyup', function() {
        let value = this.value.toLowerCase();
        fileItems.forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(value) ? '' : 'none';
        });
    });



function openFilePreview(id, name, type) {
    document.getElementById('filePreviewModal').style.display = 'flex';

    hideAllPreviews();

    document.getElementById('previewLoading').style.display = 'block';
    document.getElementById('modalFileName').innerText = name;

    fetch(`/files/${id}/previewStudentDashboard`)
        .then(res => res.json())
        .then(data => {

            if (data.error) {
                showError();
                return;
            }

            hideAllPreviews();

            let url = data.url;

            // detect extension from filename (reliable)
            let fileType = data.name.split('.').pop().toLowerCase();

            // fallback to MIME if needed
            let mimeType = (data.type || '').toLowerCase();

            // PDF
            if (fileType === 'pdf' || mimeType.includes('pdf')) {
                document.getElementById('pdfPreview').style.display = 'block';
                document.getElementById('pdfFrame').src = url;
                document.getElementById('downloadPdfBtn').onclick = function(e) {
    e.preventDefault();
    forceDownload(url, data.name);
};
            }

            // IMAGES
            else if (
                ['jpg','jpeg','png','gif','webp'].includes(fileType) ||
                mimeType.includes('image')
            ) {
                document.getElementById('imagePreview').style.display = 'block';

                const img = document.getElementById('imageViewer');
                img.src = url;

                document.getElementById('downloadImageBtn').onclick = function(e) {
    e.preventDefault();
    forceDownload(url, data.name);
};
            }

            // OFFICE
            else if (['doc','docx','xls','xlsx','ppt','pptx'].includes(fileType)) {
                document.getElementById('officePreview').style.display = 'block';

                let officeUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(url)}`;
                document.getElementById('officeFrame').src = officeUrl;
                document.getElementById('downloadOfficeBtn').onclick = function(e) {
    e.preventDefault();
    forceDownload(url, data.name);
};
            }

            // TEXT
            else if (['txt','csv','json','log'].includes(fileType)) {
                document.getElementById('textPreview').style.display = 'block';
                document.getElementById('downloadTextBtn').onclick = function(e) {
    e.preventDefault();
    forceDownload(url, data.name);
};

                fetch(url)
                    .then(res => res.text())
                    .then(text => {
                        document.getElementById('textViewer').innerText = text;
                    });
            }

            // FALLBACK
            else {
                document.getElementById('genericPreview').style.display = 'block';
                document.getElementById('downloadGenericBtn').onclick = function(e) {
    e.preventDefault();
    forceDownload(url, data.name);
};
            }

        })
        .catch(() => {
            showError();
        });
}


function hideAllPreviews() {
    document.getElementById('previewLoading').style.display = 'none';
    document.getElementById('pdfPreview').style.display = 'none';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('officePreview').style.display = 'none';
    document.getElementById('textPreview').style.display = 'none';
    document.getElementById('genericPreview').style.display = 'none';
    document.getElementById('previewError').style.display = 'none';
}

function showError() {
    hideAllPreviews();
    document.getElementById('previewError').style.display = 'block';
}

function closeFilePreview() {
    document.getElementById('filePreviewModal').style.display = 'none';
    hideAllPreviews();

    document.getElementById('pdfFrame').src = '';
    document.getElementById('officeFrame').src = '';
}

function forceDownload(url, filename) {
    fetch(url)
        .then(response => response.blob())
        .then(blob => {
            const blobUrl = window.URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();

            a.remove();
            window.URL.revokeObjectURL(blobUrl);
        })
        .catch(() => alert('Download failed'));
}
</script>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
@endsection
