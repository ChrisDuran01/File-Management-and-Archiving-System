<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @php
        // DomPDF is most reliable with images inlined as data URIs rather
        // than pointed at a local/public path - avoids chroot/remote-fetch
        // config entirely.
        $dataUri = function (?string $path) {
            if (! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return null;
            }

            $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        };

        $borderUri = $letterhead ? $dataUri($letterhead->border_image_path) : null;
        $logoUri = $letterhead ? $dataUri($letterhead->logo_path) : null;
        $sealUri = $letterhead ? $dataUri($letterhead->seal_path) : null;
        $footerUri = $letterhead ? $dataUri($letterhead->footer_image_path) : null;
    @endphp
    <style>
        {{-- Zero page margin when a letterhead is present: the border image
             needs to be a true 100% x 100% of the actual physical page, not
             the inset content box margin would otherwise create - percentage
             sizing resolves against whichever box is in play, so a nonzero
             @page margin was making the image resolve too small and land in
             the wrong place. Text is inset via plain padding instead. --}}
        @page { margin: {{ $letterhead ? '0' : '70px 60px' }}; }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #1a1a1a;
        }
        .page-content {
            padding: {{ $letterhead ? '165px 70px 120px' : '0' }};
        }
        .doc-body p { margin: 0 0 12pt; word-wrap: break-word; }

        .doc-heading {
            text-align: center;
            font-weight: bold;
            margin: 0 0 6pt;
        }
        .doc-title {
            text-align: center;
            font-weight: bold;
            margin: 4pt 0 16pt;
        }
        .doc-meta {
            margin: 0 0 4pt;
        }
        .doc-clause {
            text-align: justify;
            text-indent: 36pt;
            margin: 0 0 12pt;
        }
        .doc-paragraph {
            text-align: justify;
        }

        .lh-border {
            position: fixed;
            z-index: -1;
        }
        .lh-header {
            position: fixed;
            top: 30px; left: 70px; right: 70px;
            height: 120px;
            text-align: center;
        }
        .lh-logo { position: absolute; left: 0; top: 0; height: 90px; }
        .lh-seal { position: absolute; right: 0; top: 0; height: 90px; }
        .lh-org-text { font-size: 11pt; font-weight: bold; line-height: 1.4; padding-top: 96px; }
        .lh-footer {
            position: fixed;
            bottom: 20px; left: 0; right: 0;
            text-align: center;
        }
        .lh-footer img { max-height: 60px; }
    </style>
</head>
<body>
    @if($letterhead)
        @if($borderUri)
            {{-- Sized/positioned per borderBox (computed in Letterhead::borderCoverBox())
                 so the image always covers the page without ever distorting,
                 whatever its native aspect ratio actually is. --}}
            <img src="{{ $borderUri }}" class="lh-border" style="
                width: {{ $borderBox['width'] }}pt;
                height: {{ $borderBox['height'] }}pt;
                left: {{ $borderBox['left'] }}pt;
                top: {{ $borderBox['top'] }}pt;
            ">
        @endif

        <div class="lh-header">
            @if($logoUri)<img src="{{ $logoUri }}" class="lh-logo">@endif
            @if($sealUri)<img src="{{ $sealUri }}" class="lh-seal">@endif
            <div class="lh-org-text">
                @if($letterhead->university_name)<div>{{ $letterhead->university_name }}</div>@endif
                @if($letterhead->office_name)<div>{{ $letterhead->office_name }}</div>@endif
            </div>
        </div>

        @if($footerUri)
            <div class="lh-footer"><img src="{{ $footerUri }}"></div>
        @endif
    @endif

    <div class="page-content">
        <div class="doc-body">{!! $content !!}</div>
    </div>
</body>
</html>
