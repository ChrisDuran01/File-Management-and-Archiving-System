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

        // Page margins in points. A resolution runs to three pages, so the
        // text is kept clear of the letterhead's header and footer with real
        // page margins (they repeat on every page), and the letterhead's
        // pieces are pulled back out into the margins - fixed elements are
        // placed from the inside of the margins (same as Admin/proposals/pdf).
        $mt = $letterhead ? 128 : 72;
        $mb = $letterhead ? 100 : 72;
        $ms = 72;
    @endphp
    <style>
        @page { margin: {{ $mt }}pt {{ $ms }}pt {{ $mb }}pt {{ $ms }}pt; }
        body { font-size: 11pt; }

        @include('Admin.templates.documents.styles')

        .lh-border { position: fixed; z-index: -1; }
        .lh-header {
            position: fixed;
            top: {{ 22 - $mt }}pt; left: {{ 52 - $ms }}pt; right: {{ 52 - $ms }}pt;
            height: 90pt;
            text-align: center;
        }
        .lh-logo { position: absolute; left: 0; top: 0; height: 68pt; }
        .lh-seal { position: absolute; right: 0; top: 0; height: 68pt; }
        .lh-org-text { font-family: 'DejaVu Serif', serif; font-size: 11pt; font-weight: bold; line-height: 1.4; padding-top: 72pt; }
        .lh-footer {
            position: fixed;
            bottom: {{ 15 - $mb }}pt; left: -{{ $ms }}pt; right: -{{ $ms }}pt;
            text-align: center;
        }
        .lh-footer img { max-height: 45pt; }
    </style>
</head>
<body class="doc-sheet">
    @if($letterhead)
        @if($borderUri)
            {{-- Sized per borderBox (Letterhead::borderCoverBox) so the image
                 covers the sheet without distorting, moved back by the margins. --}}
            <img src="{{ $borderUri }}" class="lh-border" style="
                width: {{ $borderBox['width'] }}pt;
                height: {{ $borderBox['height'] }}pt;
                left: {{ $borderBox['left'] - $ms }}pt;
                top: {{ $borderBox['top'] - $mt }}pt;
            ">
        @endif

        @if($logoUri || $sealUri || $letterhead->university_name || $letterhead->office_name)
            <div class="lh-header">
                @if($logoUri)<img src="{{ $logoUri }}" class="lh-logo">@endif
                @if($sealUri)<img src="{{ $sealUri }}" class="lh-seal">@endif
                <div class="lh-org-text">
                    @if($letterhead->university_name)<div>{{ $letterhead->university_name }}</div>@endif
                    @if($letterhead->office_name)<div>{{ $letterhead->office_name }}</div>@endif
                </div>
            </div>
        @endif

        @if($footerUri)
            <div class="lh-footer"><img src="{{ $footerUri }}"></div>
        @endif
    @endif

    {!! $content !!}
</body>
</html>
