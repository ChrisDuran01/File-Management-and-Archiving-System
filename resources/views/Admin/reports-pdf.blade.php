<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @php
        // The open Reports tab on paper: the same titles, answers and role
        // rules as the page, with each chart turned into a table. Left out:
        // the names and IP addresses under Suspicious sign-ins (only the
        // counts are printed - a printed page gets passed around).

        // DomPDF is most reliable with images inlined as data URIs (same as
        // Admin/proposals/pdf).
        $dataUri = function (?string $path) {
            if (! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return null;
            }
            $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        };
        $borderUri = $letterhead ? $dataUri($letterhead->border_image_path) : null;

        // Real page margins (they repeat on every page), with the full-page
        // letterhead image pulled back out into them - as in the proposals.
        $mt = $borderUri ? 128 : 60;
        $mb = $borderUri ? 100 : 60;
        $ms = 60;

        $n = fn ($value) => number_format((float) $value);
        $delta = fn ($k) => $k['delta'] === null ? '—' : ($k['delta'] > 0 ? '+' : '').$k['delta'].'%';

        // A bar for a table cell, as long as $value is against $max.
        $bar = fn ($value, $max, string $color = '#2f6fb5') => '<div class="bar" style="width:'
            .max(1, (int) round($value / max(1, $max) * 100)).'%; background:'.$color.';"></div>';

        // Light grey to SG green, for the time-of-day grids.
        $heat = function ($value, $max) {
            if ($value <= 0 || $max <= 0) {
                return '#eef0ee';
            }
            $t = 0.15 + 0.85 * $value / $max;
            [$r1, $g1, $b1] = [0xee, 0xf0, 0xee];
            [$r2, $g2, $b2] = [0x05, 0x80, 0x28];

            $mix = fn ($from, $to) => (int) round($from + ($to - $from) * $t);

            return sprintf('#%02x%02x%02x', $mix($r1, $r2), $mix($g1, $g2), $mix($b1, $b2));
        };
        $heatText = fn ($value, $max) => $max > 0 && $value / $max > 0.55 ? '#fff' : '#111';
        $hourName = fn (int $h) => $h === 0 ? '12am' : ($h === 12 ? '12pm' : ($h % 12).($h < 12 ? 'am' : 'pm'));

        // A long period has many empty days; past 16 rows only the ones with
        // something in them are printed, and the rest are counted.
        $timeRows = function (array $labels, array ...$columns) {
            $rows = [];
            foreach ($labels as $i => $label) {
                $rows[] = ['label' => $label, 'values' => array_map(fn ($c) => $c[$i] ?? 0, $columns)];
            }
            if (count($rows) <= 16) {
                return [$rows, 0];
            }
            $kept = array_values(array_filter($rows, fn ($r) => array_sum($r['values']) > 0));

            return [$kept, count($rows) - count($kept)];
        };

        $title = match ($tab) {
            'engagement' => 'STUDENT ENGAGEMENT REPORT',
            'proposals'  => 'ACTIVITY PROPOSALS BUDGET REPORT',
            default      => 'RECORDS AND SYSTEM REPORT',
        };
    @endphp
    <style>
        @page { margin: {{ $mt }}pt {{ $ms }}pt {{ $mb }}pt {{ $ms }}pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.4; color: #111; }

        .lh-border { position: fixed; z-index: -1; }

        h1 { text-align: center; font-size: 12.5pt; margin: 0 0 8pt; letter-spacing: .5pt; }
        h2 { font-size: 10.5pt; margin: 14pt 0 4pt; padding-bottom: 2pt; border-bottom: 0.75pt solid #222; page-break-after: avoid; }
        h3 { font-size: 9.5pt; margin: 0 0 2pt; }
        h3 .scope { font-weight: normal; font-size: 8pt; color: #555; }
        p { margin: 0 0 4pt; }
        .answer { margin: 0 0 5pt; }
        .note { font-size: 8pt; color: #555; margin: 3pt 0 0; }

        /* A group's h2 sits inside its first panel: DomPDF doesn't honour
           page-break-after: avoid, so a heading could end a page alone. */
        .panel { margin: 0 0 11pt; page-break-inside: avoid; }

        .facts { border-collapse: collapse; margin: 0 0 6pt; }
        .facts td { padding: 0 8pt 1pt 0; vertical-align: top; }
        .facts .k { color: #444; }

        table.data { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.data th, table.data td { border: 0.5pt solid #999; padding: 2pt 5pt; vertical-align: middle; }
        table.data th { background: #f0f0f0; text-align: left; font-weight: bold; }
        table.data tr { page-break-inside: avoid; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data .bar-cell { width: 34%; }
        .bar { height: 6pt; }
        .muted { color: #555; }

        table.half { width: 60%; }

        table.heat { width: 100%; border-collapse: collapse; font-size: 6.5pt; table-layout: fixed; }
        table.heat td, table.heat th { padding: 2pt 0; text-align: center; border: 0.75pt solid #fff; }
        table.heat th { font-weight: normal; color: #444; }
        table.heat .day { width: 26pt; text-align: left; font-size: 7.5pt; color: #333; }
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

    <h1>{{ $title }}</h1>

    <table class="facts">
        <tr><td class="k">Period:</td><td><b>{{ $periodLabel }}</b></td></tr>
        @if ($tab !== 'proposals')
            <tr><td class="k">Compared with:</td><td>{{ $comparedWith }}</td></tr>
        @endif
        <tr><td class="k">Generated:</td><td>{{ $generatedAt->format('F j, Y, g:i A') }} by {{ $generatedBy }}</td></tr>
    </table>

@if ($tab === 'records')

    {{-- SUMMARY --}}
    <div class="panel">
        <h2>Summary</h2>
        <table class="data">
            <thead><tr><th></th><th class="num">This period</th><th class="num">Compared period</th><th class="num">Change</th></tr></thead>
            <tbody>
                @foreach ($kpis as $k)
                    <tr><td>{{ $k['label'] }}</td><td class="num"><b>{{ $n($k['value']) }}</b></td><td class="num">{{ $n($k['previous']) }}</td><td class="num">{{ $delta($k) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $withActions = isset($trend['actions']);
        [$trendRows, $emptyBuckets] = $withActions
            ? $timeRows($trend['labels'], $trend['uploads'], $trend['documents'], $trend['actions'])
            : $timeRows($trend['labels'], $trend['uploads'], $trend['documents']);
    @endphp
    <div class="panel">
        <h3>Records added</h3>
        <p class="answer">{!! $answers['trend'] !!}</p>
        @if ($trendRows)
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ ucfirst($buckets['unit']) }}</th>
                        <th class="num">Files uploaded</th>
                        <th class="num">Documents digitized</th>
                        @if ($withActions)<th class="num">Logged actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trendRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @foreach ($row['values'] as $value)
                                <td class="num">{{ $n($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if ($emptyBuckets)
            <p class="note">The other {{ $emptyBuckets }} {{ Str::plural($buckets['unit'], $emptyBuckets) }} had nothing.</p>
        @endif
    </div>

    @if ($isSuperAdmin)
        <div class="panel">
            <h3>Officer activity</h3>
            <p class="answer">{!! $answers['categories'] !!} {!! $answers['officers'] !!}</p>
            @if ($activityByCategory->isNotEmpty())
                @php $catMax = $activityByCategory->max('total'); @endphp
                <table class="data">
                    <thead><tr><th>Type</th><th class="num">Actions</th><th class="bar-cell"></th></tr></thead>
                    <tbody>
                        @foreach ($activityByCategory as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="num">{{ $n($row['total']) }}</td><td>{!! $bar($row['total'], $catMax, $row['label'] === 'Failed sign-ins' ? '#b3412f' : '#2f6fb5') !!}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if ($activeOfficers->isNotEmpty())
                <table class="data" style="margin-top:6pt;">
                    <thead><tr><th>Most active officers</th><th class="num">Actions</th><th>Last seen</th></tr></thead>
                    <tbody>
                        @foreach ($activeOfficers->take(5) as $o)
                            <tr><td>{{ $o->user_name }}</td><td class="num">{{ $n($o->total) }}</td><td>{{ $o->last_seen->format('M j, Y, g:i A') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- SCANNING + STORAGE --}}
    @php $hours = $pipeline['turnaround']; @endphp
    <div class="panel">
        <h2>Scanning and storage</h2>
        <h3>Scanning</h3>
        <p class="answer">{!! $answers['pipeline'] !!}</p>
        <table class="data half">
            <tbody>
                <tr><td>Scan batches</td><td class="num">{{ $n($pipeline['batches']) }} ({{ $n($pipeline['pages']) }} pages)</td></tr>
                <tr><td>Filed</td><td class="num">{{ $n($pipeline['filed']) }} ({{ $n($pipeline['docs_from_scans']) }} documents)</td></tr>
                <tr><td>Waiting for review now</td><td class="num">{{ $n($pipeline['awaiting']) }}</td></tr>
                <tr><td>Failed</td><td class="num">{{ $n($pipeline['failed']) }}</td></tr>
                <tr><td>Scan to filed</td><td class="num">{{ $hours === null ? '—' : ($hours < 1 ? max(1, (int) round($hours * 60)).' min' : $hours.' h') }}</td></tr>
            </tbody>
        </table>
        <p class="note">Digitized: {!! $answers['types'] !!}</p>
    </div>

    <div class="panel">
        <h3>Storage <span class="scope">(last 6 months)</span></h3>
        <p class="answer">{!! $answers['storage'] !!}</p>
        <table class="data half">
            <thead><tr><th>Month</th><th class="num">Storage used</th></tr></thead>
            <tbody>
                @foreach ($storageTrend as $point)
                    <tr><td>{{ $point['label'] }}</td><td class="num">{{ $point['formatted'] }}</td></tr>
                @endforeach
                @foreach ($projection as $point)
                    <tr><td class="muted">{{ $point['label'] }} (expected)</td><td class="num muted">{{ $point['formatted'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if ($topFolders->isNotEmpty())
            <table class="data half" style="margin-top:6pt;">
                <thead><tr><th>Largest folders</th><th class="num">Size</th></tr></thead>
                <tbody>
                    @foreach ($topFolders->take(3) as $f)
                        <tr><td>{{ $f->name }}</td><td class="num">{{ $f->formatted }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- SYSTEM HEALTH: archives, backups (Super Admin), text search --}}
    <div class="panel">
        <h2>Upkeep</h2>
        <h3>System health</h3>
        <table class="data">
            <thead><tr><th style="width:24%;">Check</th><th>Finding</th></tr></thead>
            <tbody>
                <tr><td><b>Archive integrity</b></td><td>{!! $answers['integrity'] !!}</td></tr>
                @if ($isSuperAdmin)
                    <tr><td><b>Backups</b></td><td>{!! $answers['backups'] !!}</td></tr>
                @endif
                <tr><td><b>Text search</b></td><td>{!! $answers['ocr'] !!}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- TEXT MINING - only the folders this officer may open, as on the page. --}}
    <div class="panel">
        <h2>Found in the documents’ text</h2>
        <p class="note" style="margin:0 0 6pt;">From the text of {{ $n($insights['corpus']) }} {{ Str::plural('file', $insights['corpus']) }} the person who made this report can open. Suggestions only: nothing was moved or deleted.</p>
        <h3>Possible copies <span class="scope">(all time)</span></h3>
        <p class="answer">{!! $answers['copies'] !!}</p>
        @if ($copies->isNotEmpty())
            <table class="data">
                <thead><tr><th>File</th><th>Folder</th><th>Copy of</th><th class="num">Alike</th></tr></thead>
                <tbody>
                    @foreach ($copies->take(20) as $c)
                        <tr>
                            <td>{{ $c->name }}</td>
                            <td>{{ $c->folder }}</td>
                            <td>{{ $c->original ? $c->original->name.($c->original->folder ? ' ('.$c->original->folder.')' : '') : 'a file in a folder you can’t open' }}</td>
                            <td class="num">{{ $c->similarity === 100 ? 'Exact' : $c->similarity.'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($copies->count() > 20)
                <p class="note">And {{ $copies->count() - 20 }} more.</p>
            @endif
        @endif
    </div>

    <div class="panel">
        <h3>Possibly in the wrong folder <span class="scope">(all time)</span></h3>
        <p class="answer">{!! $answers['misfiled'] !!}</p>
        @if ($insights['misfiled'])
            <table class="data">
                <thead><tr><th>File</th><th>In folder</th><th>Why</th></tr></thead>
                <tbody>
                    @foreach (array_slice($insights['misfiled'], 0, 20) as $m)
                        <tr>
                            <td>{{ $m['file']['name'] }}</td>
                            <td>{{ $m['file']['folder'] }}</td>
                            <td>
                                @if ($m['reason'] === 'title')
                                    Titled as a {{ $m['kind'] }}; belongs in {{ $m['suggested']['name'] }}
                                @else
                                    Reads like {{ $m['suggested']['name'] }} ({{ $m['other_match'] }}% vs {{ $m['own_match'] }}% for its own folder)
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (count($insights['misfiled']) > 20)
                <p class="note">And {{ count($insights['misfiled']) - 20 }} more.</p>
            @endif
        @endif
    </div>

    @if ($isSuperAdmin)
        <div class="panel">
            <h2>Security</h2>
            <h3>Suspicious sign-ins <span class="scope">(last 7 days)</span></h3>
            <p class="answer">{!! $answers['security'] ?? '' !!}</p>
            <table class="data half">
                <tbody>
                    <tr><td>Possible brute force</td><td class="num">{{ count($loginAnomalies['bruteForce']) }}</td></tr>
                    <tr><td>Multiple IPs, same account</td><td class="num">{{ count($loginAnomalies['multiIp']) }}</td></tr>
                    <tr><td>New-location logins</td><td class="num">{{ count($loginAnomalies['newLocation']) }}</td></tr>
                </tbody>
            </table>
            <p class="note">The accounts and IP addresses are on the Reports page.</p>
        </div>
    @endif

@elseif ($tab === 'engagement')
    @php $e = $engagement; @endphp

    <p class="note" style="margin:0 0 6pt;">
        Anonymous; officers' own visits aren't counted.
        {{ $e['since'] ? 'Recording since '.$e['since']->format('F j, Y').'.' : 'Nothing recorded yet.' }}
    </p>

    <div class="panel">
        <h2>Summary</h2>
        <table class="data">
            <thead><tr><th></th><th class="num">This period</th><th class="num">Compared period</th><th class="num">Change</th></tr></thead>
            <tbody>
                @foreach ($e['kpis'] as $k)
                    <tr>
                        <td>{{ $k['label'] }}</td>
                        <td class="num"><b>{{ $n($k['value']) }}</b></td>
                        <td class="num">{{ $e['comparable'] && $k['previous'] !== null ? $n($k['previous']) : '—' }}</td>
                        <td class="num">{{ $e['comparable'] ? $delta($k) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @unless ($e['comparable'])
            <p class="note">No change is shown: recording had not started in the compared period.</p>
        @endunless
    </div>

    @php [$visitRows, $emptyVisitBuckets] = $timeRows($e['visits']['labels'], $e['visits']['visits'], $e['visits']['fresh']); @endphp
    <div class="panel">
        <h2>Student page use</h2>
        <h3>Visits</h3>
        <p class="answer">{!! $e['visits']['answer'] !!}</p>
        @if (array_sum($e['visits']['visits']) > 0)
            <table class="data half">
                <thead><tr><th>{{ ucfirst($buckets['unit']) }}</th><th class="num">Visits</th><th class="num">First-time visitors</th></tr></thead>
                <tbody>
                    @foreach ($visitRows as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="num">{{ $n($row['values'][0]) }}</td><td class="num">{{ $n($row['values'][1]) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            @if ($emptyVisitBuckets)
                <p class="note">The other {{ $emptyVisitBuckets }} {{ Str::plural($buckets['unit'], $emptyVisitBuckets) }} had no visits.</p>
            @endif
        @endif
        <p class="note">{!! $e['devices']['answer'] !!} {!! $e['notifications']['answer'] !!}</p>
        <p class="note">A visit is one device opening the page on a given day; reloads don't count.</p>
    </div>

    <div class="panel">
        <h3>Unusual days</h3>
        <p class="answer">{!! $e['spikes']['answer'] !!}</p>
        @if ($e['spikes']['spikes'])
            <table class="data">
                <thead><tr><th>Day</th><th class="num">Visits</th><th class="num">Usual day</th><th>What happened</th></tr></thead>
                <tbody>
                    @foreach ($e['spikes']['spikes'] as $s)
                        <tr><td style="white-space:nowrap;">{{ $s['date']->format('D, M j, Y') }}</td><td class="num">{{ $n($s['visits']) }}</td><td class="num">{{ $n($s['usual']) }}</td><td>{{ $s['cause'] ?? '' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @php
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $gridMax = $e['timing']['max'] ?? 0;
    @endphp
    <div class="panel">
        <h3>When students visit</h3>
        <p class="answer">{!! $e['timing']['answer'] !!}</p>
        <table class="heat">
            <tr>
                <th class="day"></th>
                @for ($h = 0; $h < 24; $h++)<th>{{ $hourName($h) }}</th>@endfor
            </tr>
            @foreach ($e['timing']['grid'] as $wd => $hours)
                <tr>
                    <td class="day">{{ $days[$wd] }}</td>
                    @foreach ($hours as $count)
                        <td style="background: {{ $heat($count, $gridMax) }}; color: {{ $heatText($count, $gridMax) }};">{{ $count ?: '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <p class="note">Visits by day and hour; darker means more.</p>
    </div>

    <div class="panel">
        <h2>Announcements and documents</h2>
        <h3>Top announcements</h3>
        <p class="answer">{!! $e['announcements']['answer'] !!}</p>
        @if ($e['announcements']['rows'])
            <table class="data">
                <thead><tr><th>Announcement</th><th class="num">Students reached</th><th class="num">Opened in full</th><th class="num">Attachment downloads</th></tr></thead>
                <tbody>
                    @foreach ($e['announcements']['rows'] as $row)
                        <tr><td>{{ $row['title'] }}</td><td class="num">{{ $n($row['views']) }}</td><td class="num">{{ $n($row['opens']) }}</td><td class="num">{{ $n($row['downloads']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p class="note">Reach counts devices where the announcement stayed on screen for at least a second.</p>
    </div>


    <div class="panel">
        <h3>Most used documents</h3>
        <p class="answer">{!! $e['documents']['answer'] !!}</p>
        @if ($e['documents']['rows'])
            <table class="data">
                <thead><tr><th>Document</th><th class="num">Previews</th><th class="num">Downloads</th></tr></thead>
                <tbody>
                    @foreach ($e['documents']['rows'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="num">{{ $n($row['previews']) }}</td><td class="num">{{ $n($row['downloads']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <h3>Student searches</h3>
        <p class="answer">{!! $e['searches']['answer'] !!}</p>
        @if ($e['searches']['total'] > 0)
            <table class="data half">
                <thead><tr><th>Found nothing</th><th class="num">Times</th></tr></thead>
                <tbody>
                    @forelse ($e['searches']['missing'] as $s)
                        <tr><td>{{ $s->term }}</td><td class="num">{{ $s->n }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="muted">None.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if (collect($e['searches']['top'])->pluck('term')->all() !== collect($e['searches']['missing'])->pluck('term')->all())
                <table class="data half" style="margin-top:6pt;">
                    <thead><tr><th>Searched most</th><th class="num">Times</th></tr></thead>
                    <tbody>
                        @foreach ($e['searches']['top'] as $s)
                            <tr><td>{{ $s->term }}</td><td class="num">{{ $s->n }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>
@else
    @php
        $pp = $proposalReport['prices'];
        $pr = $proposalReport['rules'];
        // "P 65" like the proposal PDF: DejaVu has no peso sign.
        $pdfPeso = fn ($v) => 'P '.number_format((float) $v, round((float) $v, 2) == floor((float) $v) ? 0 : 2);
        $rateText = rtrim(rtrim(number_format($pp['rate'], 2), '0'), '.');
    @endphp

    <div class="panel">
        <h3>Unusual prices</h3>
        <p class="answer">{!! $pp['answer'] !!}</p>
        @if ($pp['flagged'])
            <table class="data">
                <thead><tr><th>Proposal</th><th>Particular</th><th class="num">Unit price</th><th class="num">Usual</th><th>Check</th><th>Officer’s reason</th></tr></thead>
                <tbody>
                    @foreach ($pp['flagged'] as $f)
                        <tr>
                            <td>{{ $f['proposal']->title }}<br><span class="muted">{{ $f['proposal']->statusLabel() }}, {{ $f['proposal']->dateLabel() }}</span></td>
                            <td>{{ $f['item']->particular }}{{ $f['item']->unit ? ' (per '.$f['item']->unit.')' : '' }}</td>
                            <td class="num">{{ $pdfPeso($f['item']->unit_price) }}</td>
                            <td class="num">{{ $pdfPeso($f['check']['median']) }}</td>
                            <td>{{ str_replace('₱', 'P ', $f['check']['summary']) }}</td>
                            <td>{{ filled($f['reason']) ? $f['reason'] : 'None yet' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p class="note">Each Unit Price is compared with what approved proposals paid for the same item, older prices brought up to today’s value at {{ $rateText }}% a year, from this school year and the {{ $pp['years'] }} before. Unusual: a robust z-score (median and median absolute deviation) of 3.5 or more; an item needs 3 or more past prices.</p>
    </div>

    <div class="panel">
        <h3>Often budgeted together <span class="scope">(all approved proposals)</span></h3>
        <p class="answer">{!! $pr['answer'] !!}</p>
        @if ($pr['rules'])
            <table class="data">
                <thead><tr><th>If the budget has</th><th>It also had</th><th class="num">Together</th><th class="num">Confidence</th><th class="num">Lift</th></tr></thead>
                <tbody>
                    @foreach ($pr['rules'] as $r)
                        <tr>
                            <td>{{ $r['if_label'] }}</td>
                            <td>{{ $r['then_label'] }}</td>
                            <td class="num">{{ $r['together'] }} of {{ $pr['baskets'] }}</td>
                            <td class="num">{{ round($r['confidence'] * 100) }}% ({{ $r['together'] }} of {{ $r['with_if'] }})</td>
                            <td class="num">{{ number_format($r['lift'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p class="note">Association rules (Apriori). Confidence: of the proposals with the first items, the share that also had the second. Lift above 1: they go together more often than by chance.</p>
    </div>
@endif
</body>
</html>
