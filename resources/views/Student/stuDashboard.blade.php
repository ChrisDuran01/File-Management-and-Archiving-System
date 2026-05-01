{{-- qsu-transparency-dashboard.blade.php --}}
@extends('Student.home')

@section('content')
<div class="dashboard-container">
    {{-- Animated Background Elements --}}
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
        <div class="shape shape-5"></div>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4 position-relative" style="z-index: 2;">

        {{-- Modern Hero Section with Logo --}}
        <div class="hero-section mb-5">
            <div class="hero-logo-wrapper">
                <div class="logo-pulse">
                    <img src="{{ asset('images/SG-logo.png') }}" alt="QSU Student Government Logo" class="sg-logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/120x120?text=SG';">
                </div>
            </div>
            <h1 class="hero-title">Transparency Portal</h1>
            <p class="hero-subtitle">Official documents open to all QSU students</p>
        </div>

        {{-- Two Modern Text Boxes with SVG Icons --}}
        <div class="row g-4 mb-5">
            <div class="col-12 col-md-6">
                <div class="modern-card wow-card">
                    <div class="card-icon">
                        <img src="{{ asset('images/document-icon.png') }}" alt="Document Icon" class="info-svg">
                    </div>
                    <h3>What's inside?</h3>
                    <p>
                        Official resolutions, financial reports, meeting minutes,
                        and communications from your Student Government — all publicly accessible.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="modern-card wow-card">
                    <div class="card-icon">
                        <img src="{{ asset('images/unlock-icon.png') }}" alt="Unlocked Icon" class="info-svg">
                    </div>
                    <h3>Open access</h3>
                    <p>
                        Every QSU student has the right to review these documents.
                        Transparency and accountability are at the core of our student government.
                    </p>
                </div>
            </div>
        </div>

        {{-- Documents Section with Search --}}
        <div class="doc-section">
            <div class="doc-header">
                <div>
                    <h2 class="section-title">
                        <span class="material-icons-outlined">description</span>
                        All Documents
                    </h2>
                    <p class="section-subtitle">{{ $totalFiles }} document(s) available</p>
                </div>
                <div class="search-container" style="display:flex;align-items:center;gap:8px;">
                    <span class="material-icons-outlined">search</span>
                    <input type="text" id="search" class="modern-search" placeholder="Search documents...">
                </div>
            </div>

            {{-- Modern Document Grid --}}
            <div class="row g-3" id="fileContainer">
                @forelse($files as $file)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 file-item">
                    <div class="modern-doc-card glow-card" onclick="openFilePreview('{{ $file->id }}', '{{ addslashes($file->filename) }}', '{{ $file->type }}')">
                        <div class="doc-content">
                            <h6 class="doc-name">{{ $file->filename }}</h6>
                            <div class="doc-meta">
                                <span class="doc-tag">{{ strtoupper($file->type) }}</span>
                                <span class="doc-date">{{ $file->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                        <div class="preview-indicator">
                            👁️
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
        <div class="contact-modern mt-5">
            <div class="contact-header">
                <h2 class="section-title">
                    <span class="material-icons-outlined" style="font-size: 32px; line-height: 1;">mail_outline</span>
                    Need help?
                </h2>
                <p>Send us a message — we'll respond within 3-5 business days</p>
            </div>

            <form id="feedbackForm" class="modern-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="senderName" placeholder="Full name" class="modern-input" required>
                    </div>
                    <div class="form-group">
                        <input type="email" id="senderEmail" placeholder="Email address" class="modern-input" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="studentId" placeholder="Student ID" class="modern-input" required>
                    </div>
                </div>
                <div class="form-group full-width">
                    <textarea id="message" rows="3" placeholder="Your message..." class="modern-input modern-textarea"></textarea>
                </div>
                <button type="submit" class="modern-btn">
                    ✉️ Send message
                </button>
            </form>

            <div id="successMsg" class="modern-success" style="display: none;">
                ✓ Message sent to QSU Student Government
            </div>

            <div class="contact-footer">
                <p>Or email us directly: <strong>sg@qsu.edu.ph</strong></p>
            </div>
        </div>

    </div>
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
                <p style="margin-top: 16px; color: #6b8a5e;">Loading preview...</p>
            </div>
            
            <!-- PDF Preview -->
            <div id="pdfPreview" style="display: none;">
                <iframe id="pdfFrame" src="" style="width: 100%; height: 500px; border: none; border-radius: 12px;"></iframe>
                <div class="modal-actions">
                    <a id="downloadPdfBtn" href="#" download class="modal-btn download-btn">📥 Download PDF</a>
                </div>
            </div>
            
            <!-- Image Preview -->
            <div id="imagePreview" style="display: none;">
                <img id="imageViewer" src="" alt="File Preview" style="max-width: 100%; max-height: 500px; border-radius: 12px; object-fit: contain;">
                <div class="modal-actions">
                    <a id="downloadImageBtn" href="#" download class="modal-btn download-btn">📥 Download Image</a>
                </div>
            </div>
            
            <!-- Document Preview (Word, Excel, PPT) -->
            <div id="officePreview" style="display: none;">
                <div class="office-viewer">
                    <iframe id="officeFrame" src="" style="width: 100%; height: 550px; border: none; border-radius: 12px;"></iframe>
                </div>
                <div class="modal-actions">
                    <a id="downloadOfficeBtn" href="#" download class="modal-btn download-btn">📥 Download File</a>
                </div>
            </div>
            
            <!-- Text Preview -->
            <div id="textPreview" style="display: none;">
                <div class="text-content">
                    <pre id="textViewer" style="white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow: auto; padding: 16px; background: #f5f5f5; border-radius: 12px;"></pre>
                </div>
                <div class="modal-actions">
                    <a id="downloadTextBtn" href="#" download class="modal-btn download-btn">📥 Download File</a>
                </div>
            </div>
            
            <!-- Generic Preview -->
            <div id="genericPreview" style="display: none; text-align: center;">
                <div class="generic-file-icon">
                    📄
                </div>
                <p style="margin-top: 20px;">No preview available for this file type</p>
                <div class="modal-actions">
                    <a id="downloadGenericBtn" href="#" download class="modal-btn download-btn">📥 Download File</a>
                </div>
            </div>
            
            <!-- Error State -->
            <div id="previewError" style="display: none; text-align: center; padding: 40px;">
                <div class="error-icon">⚠️</div>
                <p style="margin-top: 16px; color: #e74c3c;">Failed to load preview. Please try again later.</p>
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
        background: #c8e0c4;
        min-height: 100vh;
        overflow-x: hidden;
    }

    .dashboard-container {
        background: #c8e0c4;
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        position: relative;
        overflow: hidden;
    }

    /* Modern Card with Preview Indicator */
    .modern-doc-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(5px);
        border-radius: 20px;
        padding: 1rem;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(46, 125, 50, 0.2);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modern-doc-card:hover {
        transform: translateY(-5px);
        border-color: #2e7d32;
        box-shadow: 0 15px 30px -12px rgba(46, 125, 50, 0.2);
    }
    .preview-indicator {
        background: rgba(46, 125, 50, 0.1);
        border-radius: 30px;
        padding: 6px 10px;
        font-size: 0.8rem;
        transition: all 0.3s;
        opacity: 0.6;
    }
    .modern-doc-card:hover .preview-indicator {
        opacity: 1;
        background: rgba(46, 125, 50, 0.2);
        transform: scale(1.05);
    }
    .doc-content {
        flex: 1;
    }
    .doc-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1a4d2a;
        margin-bottom: 8px;
    }
    .doc-meta {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .doc-tag {
        font-size: 0.65rem;
        font-weight: 600;
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #2e7d32;
        padding: 3px 10px;
        border-radius: 30px;
    }
    .doc-date {
        font-size: 0.65rem;
        color: #6b8a5e;
    }

    /* Modal Styles */
    .file-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 2000;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    .file-modal.active {
        display: flex;
    }
    .glass-modal {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 28px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        animation: slideUp 0.3s ease;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid rgba(46, 125, 50, 0.2);
        background: white;
    }
    .modal-header h3 {
        margin: 0;
        color: #1a4d2a;
        font-weight: 600;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.8rem;
        cursor: pointer;
        color: #6b8a5e;
        transition: all 0.2s;
        line-height: 1;
    }
    .modal-close:hover {
        color: #e74c3c;
        transform: scale(1.1);
    }
    .modal-body {
        padding: 1.5rem;
        max-height: calc(90vh - 80px);
        overflow-y: auto;
    }
    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .modal-btn {
        padding: 10px 24px;
        border-radius: 40px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .download-btn {
        background: linear-gradient(135deg, #1a4d2a, #2e7d32);
        color: white;
        border: none;
    }
    .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46,125,50,0.3);
    }
    .generic-file-icon {
        font-size: 5rem;
        padding: 2rem;
    }
    .office-viewer iframe {
        width: 100%;
        min-height: 500px;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .glass-modal {
            width: 95%;
        }
        .modal-body {
            padding: 1rem;
        }
        .modal-header h3 {
            font-size: 1rem;
        }
    }

    /* Animated Background Shapes */
    .bg-shapes {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: 1;
        pointer-events: none;
    }

    .shape {
        position: absolute;
        background: rgba(46, 125, 50, 0.08);
        border-radius: 50%;
        animation: float 20s infinite ease-in-out;
    }

    .shape-1 {
        width: 300px;
        height: 300px;
        top: -100px;
        left: -100px;
        animation-delay: 0s;
    }

    .shape-2 {
        width: 500px;
        height: 500px;
        bottom: -150px;
        right: -150px;
        animation-delay: 2s;
        animation-duration: 25s;
    }

    .shape-3 {
        width: 200px;
        height: 200px;
        top: 30%;
        right: 10%;
        animation-delay: 4s;
        animation-duration: 18s;
    }

    .shape-4 {
        width: 400px;
        height: 400px;
        bottom: 20%;
        left: -100px;
        animation-delay: 1s;
        animation-duration: 22s;
        background: rgba(165, 214, 167, 0.1);
    }

    .shape-5 {
        width: 250px;
        height: 250px;
        top: 60%;
        right: -50px;
        animation-delay: 3s;
        animation-duration: 15s;
        background: rgba(46, 125, 50, 0.06);
    }

    @keyframes float {
        0%, 100% {
            transform: translateY(0) translateX(0) rotate(0deg);
        }
        33% {
            transform: translateY(-30px) translateX(20px) rotate(5deg);
        }
        66% {
            transform: translateY(20px) translateX(-15px) rotate(-3deg);
        }
    }

    /* Hero Section with Logo */
    .hero-section {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
    }
    .hero-logo-wrapper {
        margin-bottom: 1rem;
    }
    .logo-pulse {
        display: inline-block;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    .sg-logo {
        width: 140px;
        height: 140px;
        object-fit: contain;
        border-radius: 50%;
        background: white;
        padding: 15px;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }
    .sg-logo:hover {
        transform: scale(1.05) rotate(5deg);
        box-shadow: 0 25px 50px -15px rgba(0,0,0,0.3);
    }
    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #1a4d2a 0%, #2e7d32 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-bottom: 0.5rem;
        letter-spacing: -0.5px;
        animation: slideDown 0.6s ease;
    }
    .hero-subtitle {
        color: #2d5a2d;
        font-size: 1.1rem;
        animation: fadeIn 0.8s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Modern Cards (Two Boxes) */
    .modern-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 1.8rem;
        height: 100%;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(46, 125, 50, 0.2);
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
    }
    .modern-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(46, 125, 50, 0.1), transparent);
        transition: left 0.5s;
    }
    .modern-card:hover::before {
        left: 100%;
    }
    .modern-card:hover {
        transform: translateY(-8px) scale(1.02);
        border-color: rgba(46, 125, 50, 0.4);
        box-shadow: 0 20px 40px -15px rgba(46, 125, 50, 0.2);
    }
    /* Replace old emoji icon style */
.card-icon {
    margin-bottom: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

.info-svg {
    width: 155px;
    height: 155px;
    object-fit: contain;
    transition: all 0.3s ease;
    animation: bounceSoft 2s infinite;
}

.modern-card:hover .info-svg {
    transform: scale(1.08);
}
    @keyframes bounceSoft {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-5px);
        }
    }
    .modern-card h3 {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a4d2a;
        margin-bottom: 0.75rem;
    }
    .modern-card p {
        font-size: 0.9rem;
        color: #3a5a3a;
        line-height: 1.6;
        margin: 0;
    }

    /* Documents Section */
    .doc-section {
        margin-top: 1rem;
        position: relative;
        z-index: 2;
    }
    .doc-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a4d2a;
        margin-bottom: 0.2rem;
    }
    .section-subtitle {
        font-size: 0.8rem;
        color: #2d5a2d;
    }
    .search-container {
        min-width: 260px;
    }
    .modern-search {
        width: 100%;
        padding: 12px 20px;
        border-radius: 50px;
        border: 2px solid rgba(46, 125, 50, 0.2);
        background: rgba(255, 255, 255, 0.95);
        font-size: 0.85rem;
        transition: all 0.3s;
        backdrop-filter: blur(5px);
    }
    .modern-search:focus {
        outline: none;
        border-color: #2e7d32;
        box-shadow: 0 0 0 5px rgba(46, 125, 50, 0.1);
        transform: scale(1.02);
    }

    /* Modern Document Cards */
    .modern-doc-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(5px);
        border-radius: 20px;
        padding: 1rem;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(46, 125, 50, 0.15);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .modern-doc-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, #2e7d32, #66bb6a);
        transition: width 0.3s ease;
    }
    .modern-doc-card:hover::after {
        width: 100%;
    }
    .modern-doc-card:hover {
        transform: translateY(-5px) scale(1.02);
        border-color: rgba(46, 125, 50, 0.3);
        box-shadow: 0 15px 30px -12px rgba(46, 125, 50, 0.2);
    }
    .doc-content {
        width: 100%;
    }
    .doc-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1a4d2a;
        margin-bottom: 8px;
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
        font-weight: 600;
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #2e7d32;
        padding: 3px 10px;
        border-radius: 30px;
    }
    .doc-date {
        font-size: 0.65rem;
        color: #6b8a5e;
    }

    /* Contact Section */
    .contact-modern {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 32px;
        padding: 2.5rem;
        border: 1px solid rgba(46, 125, 50, 0.2);
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        position: relative;
        z-index: 2;
    }
    .contact-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    .contact-header .section-title {
        margin-bottom: 0.5rem;
    }
    .contact-header p {
        font-size: 0.9rem;
        color: #4a6e3a;
    }
    .modern-form {
        max-width: 700px;
        margin: 0 auto;
    }
    .form-row {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .form-group {
        flex: 1;
        min-width: 180px;
    }
    .full-width {
        width: 100%;
        margin-bottom: 1rem;
    }
    .modern-input {
        width: 100%;
        padding: 14px 18px;
        border-radius: 16px;
        border: 2px solid rgba(46, 125, 50, 0.15);
        background: white;
        font-size: 0.85rem;
        font-family: inherit;
        transition: all 0.3s;
    }
    .modern-input:focus {
        outline: none;
        border-color: #2e7d32;
        box-shadow: 0 0 0 5px rgba(46, 125, 50, 0.08);
        transform: scale(1.01);
    }
    .modern-textarea {
        resize: vertical;
    }
    .modern-btn {
        background: linear-gradient(135deg, #1a4d2a 0%, #2e7d32 100%);
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        position: relative;
        overflow: hidden;
    }
    .modern-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }
    .modern-btn:hover::before {
        left: 100%;
    }
    .modern-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -8px #2e7d32;
    }
    .modern-success {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #1b5e2a;
        padding: 1rem;
        border-radius: 20px;
        margin-top: 1.5rem;
        text-align: center;
        font-size: 0.85rem;
        font-weight: 500;
        animation: slideUp 0.4s ease;
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .contact-footer {
        text-align: center;
        margin-top: 1.8rem;
        padding-top: 1.2rem;
        border-top: 1px solid rgba(46, 125, 50, 0.15);
        font-size: 0.8rem;
        color: #6b8a5e;
    }
    .contact-footer strong {
        color: #1a4d2a;
    }

    /* Empty State */
    .empty-modern {
        text-align: center;
        padding: 3rem;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(5px);
        border-radius: 24px;
        border: 1px solid rgba(46, 125, 50, 0.2);
    }
    .empty-modern p {
        color: #6b8a5e;
        font-size: 0.9rem;
    }

    /* Animations */
    @keyframes fadeSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .file-item {
        animation: fadeSlideUp 0.4s ease forwards;
    }

    /* Stagger animation for cards */
    .file-item:nth-child(1) { animation-delay: 0.05s; }
    .file-item:nth-child(2) { animation-delay: 0.1s; }
    .file-item:nth-child(3) { animation-delay: 0.15s; }
    .file-item:nth-child(4) { animation-delay: 0.2s; }

    /* Responsive */
    @media (max-width: 768px) {
        .hero-title {
            font-size: 2rem;
        }
        .sg-logo {
            width: 100px;
            height: 100px;
        }
        .modern-card {
            padding: 1.2rem;
        }
        .doc-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .search-container {
            width: 100%;
        }
        .contact-modern {
            padding: 1.5rem;
        }
        .form-row {
            flex-direction: column;
            gap: 0.75rem;
        }
        .modern-doc-card {
            padding: 0.75rem;
        }
        .shape-1, .shape-2, .shape-3, .shape-4, .shape-5 {
            opacity: 0.5;
        }
    }
</style>

<script>
    // Search Filter
    const searchInput = document.getElementById('search');
    const fileItems = document.querySelectorAll('.file-item');

    searchInput.addEventListener('keyup', function() {
        let value = this.value.toLowerCase();
        fileItems.forEach(item => {
            const text = item.innerText.toLowerCase();
            if (text.includes(value)) {
                item.style.display = '';
                item.style.animation = 'fadeSlideUp 0.3s ease forwards';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Email Form Handler
    const form = document.getElementById('feedbackForm');
    const successMsg = document.getElementById('successMsg');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const name = document.getElementById('senderName').value;
        const email = document.getElementById('senderEmail').value;
        const studentId = document.getElementById('studentId').value;
        const message = document.getElementById('message').value;

        const sgEmail = 'sg@qsu.edu.ph';
        const subject = encodeURIComponent(`[Transparency Portal] ${name} (${studentId})`);
        const body = encodeURIComponent(
            `Name: ${name}\nEmail: ${email}\nStudent ID: ${studentId}\n\nMessage:\n${message}`
        );

        window.location.href = `mailto:${sgEmail}?subject=${subject}&body=${body}`;

        successMsg.style.display = 'block';
        form.reset();

        setTimeout(() => {
            successMsg.style.display = 'none';
        }, 5000);
    });

     let currentFileUrl = '';
    let currentFileName = '';
    let currentFileId = '';
    
    function openFilePreview(fileId, fileName, fileType) {
        currentFileId = fileId;
        currentFileName = fileName;
        
        const modal = document.getElementById('filePreviewModal');
        const modalFileName = document.getElementById('modalFileName');
        
        modalFileName.textContent = fileName;
        
        // Hide all preview containers and show loading
        document.getElementById('previewLoading').style.display = 'block';
        document.getElementById('pdfPreview').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('officePreview').style.display = 'none';
        document.getElementById('textPreview').style.display = 'none';
        document.getElementById('genericPreview').style.display = 'none';
        document.getElementById('previewError').style.display = 'none';
        
        // Show modal
        modal.classList.add('active');
        modal.style.display = 'flex';
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Call backend to get signed URL
        fetch(`/files/${fileId}/preview`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to get preview URL');
            }
            return response.json();
        })
        .then(data => {
            // Hide loading
            document.getElementById('previewLoading').style.display = 'none';
            
            if (data.signedUrl) {
                currentFileUrl = data.signedUrl;
                showPreviewByFileType(fileType, data.signedUrl);
            } else {
                throw new Error('No signed URL received');
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            document.getElementById('previewLoading').style.display = 'none';
            document.getElementById('previewError').style.display = 'block';
        });
    }
    
    function showPreviewByFileType(fileType, fileUrl) {
        const extension = fileType.toLowerCase();
        
        if (extension === 'pdf') {
            // PDF Preview
            document.getElementById('pdfPreview').style.display = 'block';
            const pdfFrame = document.getElementById('pdfFrame');
            pdfFrame.src = fileUrl;
            document.getElementById('downloadPdfBtn').href = fileUrl;
        } 
        else if (extension === 'jpg' || extension === 'jpeg' || extension === 'png' || extension === 'gif' || extension === 'webp' || extension === 'bmp') {
            // Image Preview
            document.getElementById('imagePreview').style.display = 'block';
            const imageViewer = document.getElementById('imageViewer');
            imageViewer.src = fileUrl;
            document.getElementById('downloadImageBtn').href = fileUrl;
        }
        else if (extension === 'doc' || extension === 'docx' || extension === 'xls' || extension === 'xlsx' || extension === 'ppt' || extension === 'pptx') {
            // Office Preview using Google Docs Viewer
            document.getElementById('officePreview').style.display = 'block';
            const viewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fileUrl)}&embedded=true`;
            document.getElementById('officeFrame').src = viewerUrl;
            document.getElementById('downloadOfficeBtn').href = fileUrl;
        }
        else if (extension === 'txt' || extension === 'csv' || extension === 'json' || extension === 'xml') {
            // Text Preview
            document.getElementById('textPreview').style.display = 'block';
            fetch(fileUrl)
                .then(response => response.text())
                .then(text => {
                    document.getElementById('textViewer').textContent = text;
                })
                .catch(error => {
                    document.getElementById('textViewer').textContent = 'Unable to load file content';
                });
            document.getElementById('downloadTextBtn').href = fileUrl;
        }
        else {
            // Generic Preview
            document.getElementById('genericPreview').style.display = 'block';
            document.getElementById('downloadGenericBtn').href = fileUrl;
        }
    }
    
    function closeFilePreview() {
        const modal = document.getElementById('filePreviewModal');
        modal.classList.remove('active');
        modal.style.display = 'none';
        
        // Clear iframe sources to stop loading
        document.getElementById('pdfFrame').src = '';
        document.getElementById('officeFrame').src = '';
        document.getElementById('imageViewer').src = '';
        document.getElementById('textViewer').textContent = '';
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('filePreviewModal');
        if (event.target === modal) {
            closeFilePreview();
        }
    }
    
    
</script>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
@endsection
