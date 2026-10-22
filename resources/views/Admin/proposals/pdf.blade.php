<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @php
        // Laid out after a real SG proposal (FRESHIVAL 2026): heading
        // fields, parts I-V, then the signatories, on the SG letterhead.

        // DomPDF is most reliable with images inlined as data URIs (same as
        // Admin/templates-pdf).
        $dataUri = function (?string $path) {
            if (! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return null;
            }
            $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        };
        $borderUri = $letterhead ? $dataUri($letterhead->border_image_path) : null;

        // Page margins in points. A proposal runs several pages, so the text
        // is kept clear of the letterhead with real page margins (they repeat
        // on every page) and the full-page letterhead image is pulled back
        // out into them.
        $mt = $borderUri ? 128 : 72;
        $mb = $borderUri ? 100 : 72;
        $ms = 64;

        $money = fn ($n) => number_format((float) $n, 2);
        // As typed, without trailing zeros or a thousands separator: 60, 25000, 2.5
        $plain = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $paragraphs = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $text))));
        $lines = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), fn ($l) => $l !== ''));

        // Rows next to each other with the same Category look like one
        // merged cell: no lines between them, the name on the middle row.
        // (A real rowspan breaks in DomPDF when the group crosses a page.)
        $items = $proposal->items->values();
        $budgetRows = [];
        $i = 0;
        while ($i < $items->count()) {
            $category = trim((string) $items[$i]->category);
            $j = $i;
            while ($category !== '' && $j + 1 < $items->count() && trim((string) $items[$j + 1]->category) === $category) {
                $j++;
            }
            $middle = intdiv($i + $j, 2);
            for ($k = $i; $k <= $j; $k++) {
                $budgetRows[] = [
                    'item'     => $items[$k],
                    'category' => $k === $middle ? $category : '',
                    'top'      => $k === $i,
                    'bottom'   => $k === $j,
                ];
            }
            $i = $j + 1;
        }
    @endphp
    <style>
        @page { margin: {{ $mt }}pt {{ $ms }}pt {{ $mb }}pt {{ $ms }}pt; }
        body { font-family: 'DejaVu Serif', serif; font-size: 10.5pt; line-height: 1.4; color: #111; }

        .lh-border { position: fixed; z-index: -1; }

        h1 { text-align: center; font-size: 13pt; margin: 0 0 10pt; }
        h2 { font-size: 11pt; margin: 14pt 0 5pt; page-break-after: avoid; }
        p { margin: 0 0 6pt; text-align: justify; text-indent: 26pt; }
        ol { margin: 2pt 0 6pt; padding-left: 30pt; }
        ol li { margin-bottom: 2pt; text-align: justify; }

        .facts { border-collapse: collapse; margin-bottom: 6pt; }
        .facts td { padding: 0 0 1pt; vertical-align: top; }
        .facts .k { width: 74pt; }
        .facts .v { font-weight: bold; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 0.75pt solid #222; padding: 3pt 5pt; vertical-align: top; }
        table.grid th { text-align: center; font-weight: bold; }
        table.grid tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .budget td { font-size: 9.5pt; }
        .budget .cat { text-align: center; vertical-align: middle; }
        .budget .open-top { border-top: none; }
        .budget .open-bottom { border-bottom: none; }
        .grand td { font-weight: bold; }

        .sign-group { page-break-inside: avoid; }
        .sign-label { margin: 18pt 0 0; }
        table.signers { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        table.signers td { width: 50%; text-align: center; vertical-align: top; padding: 26pt 4pt 0; }
        .signer-name { font-weight: bold; }
        .signer-position { font-style: italic; font-size: 9.5pt; }
    </style>
</head>
<body>
    @if ($borderUri)
        {{-- Covers the whole sheet (Letterhead::borderCoverBox), moved back by the page margins. --}}
        <img src="{{ $borderUri }}" class="lh-border" style="
            width: {{ $borderBox['width'] }}pt;
            height: {{ $borderBox['height'] }}pt;
            left: {{ $borderBox['left'] - $ms }}pt;
            top: {{ $borderBox['top'] - $mt }}pt;
        ">
    @endif

    <h1>DETAILED ACTIVITY PROPOSAL</h1>

    <table class="facts">
        <tr><td class="k">Title:</td><td class="v">{{ $proposal->title }}</td></tr>
        @if ($proposal->theme)
            <tr><td class="k">Theme:</td><td class="v">{{ $proposal->theme }}</td></tr>
        @endif
        <tr><td class="k">Date:</td><td class="v">{{ $proposal->starts_on ? $proposal->dateLabel() : '' }}</td></tr>
        <tr><td class="k">Proponent:</td><td class="v">{{ $proposal->proponent }}</td></tr>
        {{-- "P", as the SG writes it: DomPDF's fonts can't be relied on for the peso sign. --}}
        <tr><td class="k">Budget:</td><td class="v">P {{ $money($proposal->budgetTotal()) }}</td></tr>
    </table>

    <h2>I.&nbsp;&nbsp;Rationale</h2>
    @foreach ($paragraphs($proposal->rationale) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    <h2>II.&nbsp;&nbsp;Objectives</h2>
    @foreach ($paragraphs($proposal->objectives_intro) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
    @if (! empty($proposal->objectives))
        <ol>
            @foreach ($proposal->objectives as $objective)
                <li>{{ $objective }}</li>
            @endforeach
        </ol>
    @endif

    <h2>III.&nbsp;&nbsp;Methods &amp; Approaches</h2>
    @foreach ($paragraphs($proposal->methods) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    <h2>IV.&nbsp;&nbsp;Schedule of Activities</h2>
    @if (! empty($proposal->schedule) || $proposal->master_of_ceremony)
        <table class="grid">
            <thead>
                <tr><th style="width:24%;">Time</th><th>Activity</th><th style="width:32%;">Venue/Personnel</th></tr>
            </thead>
            <tbody>
                @foreach ($proposal->schedule ?? [] as $row)
                    @php $activity = $lines($row['activity'] ?? ''); @endphp
                    <tr>
                        <td class="center">{{ $row['time'] ?? '' }}</td>
                        <td>
                            @foreach ($activity as $n => $line)
                                @if ($n === 0)<b>{{ $line }}</b>@else<br>{{ $line }}@endif
                            @endforeach
                        </td>
                        <td class="center">
                            @foreach ($lines($row['personnel'] ?? $row['in_charge'] ?? '') as $n => $line)
                                @if ($n > 0)<br>@endif{{ $line }}
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                @if ($proposal->master_of_ceremony)
                    <tr><td colspan="3" class="center">{{ $proposal->master_of_ceremony }}<br><i>Master of Ceremony</i></td></tr>
                @endif
            </tbody>
        </table>
    @endif

    <h2>V.&nbsp;&nbsp;Budgetary Requirements</h2>
    <p>Here are the following needs for this activity:</p>
    @if ($proposal->items->isNotEmpty())
        <table class="grid budget">
            <thead>
                <tr>
                    <th style="width:17%;">Category</th>
                    <th>Particular</th>
                    <th style="width:11%;">Quantity</th>
                    <th style="width:10%;">Unit</th>
                    <th style="width:11%;">Unit Price</th>
                    <th style="width:15%;">Total Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($budgetRows as $row)
                    <tr>
                        <td class="cat {{ $row['top'] ? '' : 'open-top' }} {{ $row['bottom'] ? '' : 'open-bottom' }}">{{ $row['category'] }}</td>
                        <td>{{ $row['item']->particular }}</td>
                        <td class="center">{{ $plain($row['item']->quantity) }}</td>
                        <td class="center">{{ $row['item']->unit }}</td>
                        <td class="center">{{ $plain($row['item']->unit_price) }}</td>
                        <td class="num">{{ $money($row['item']->totalPrice()) }}</td>
                    </tr>
                @endforeach
                <tr class="grand">
                    <td colspan="5" class="num">Grand Total</td>
                    <td class="num">{{ $money($proposal->budgetTotal()) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Each group stays on one page with its label, so "Recommending
         Approval:" never sits alone at the foot of a page. --}}
    @foreach ($proposal->signatoryGroups() as $group)
        <div class="sign-group">
            <div class="sign-label">{{ $group['label'] }}:</div>
            <table class="signers">
                @foreach (array_chunk($group['people'], 2) as $pair)
                    <tr>
                        @foreach ($pair as $person)
                            <td @if (count($pair) === 1) colspan="2" @endif>
                                <div class="signer-name">{{ $person['name'] ?: '______________________' }}</div>
                                <div class="signer-position">{{ $person['position'] }}</div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
