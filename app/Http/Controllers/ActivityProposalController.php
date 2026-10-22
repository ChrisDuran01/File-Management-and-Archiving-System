<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\ActivityProposalItem;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Models\OfficerTerm;
use App\Support\SchoolYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Detailed Activity Proposals: write one, read it, print it as a PDF on the
 * SG letterhead, copy last year's into a new draft, change or delete your
 * own. Laid out after a real SG proposal (FRESHIVAL 2026). Signing happens
 * on paper; approval in the system and the student page are not built.
 */
class ActivityProposalController extends Controller
{
    public function index(Request $request)
    {
        $years = ActivityProposal::whereNotNull('school_year')->distinct()->pluck('school_year')
            ->push(SchoolYear::current())
            ->unique()->sortDesc()->values();

        // Defaults to this school year; "all" shows every year, which is
        // where last year's proposals are found for copying.
        $year = $request->query('year', SchoolYear::current());
        $search = trim((string) $request->query('q', ''));

        $proposals = ActivityProposal::with(['items', 'creator', 'copiedFrom'])
            ->when($year !== 'all', fn ($q) => $q->where('school_year', $year))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('theme', 'like', $like)
                    ->orWhereHas('items', fn ($i) => $i->where('particular', 'like', $like)->orWhere('category', 'like', $like)));
            })
            ->orderByDesc('starts_on')
            ->orderByDesc('updated_at')
            ->get();

        return view('Admin.proposals.index', [
            'layout'    => $this->layout(),
            'proposals' => $proposals,
            'years'     => $years,
            'year'      => $year,
            'search'    => $search,
        ]);
    }

    public function create()
    {
        return view('Admin.proposals.form', $this->formData(new ActivityProposal([
            'proponent'   => 'Student Government',
            'signatories' => ActivityProposal::defaultSignatories(),
        ])));
    }

    public function store(Request $request)
    {
        [$fields, $items] = $this->validated($request);

        $term = OfficerTerm::activeFor(Auth::id());

        $proposal = DB::transaction(function () use ($fields, $items, $term) {
            $proposal = ActivityProposal::create($fields + [
                'school_year'          => $this->schoolYearFor($fields['starts_on'], $term),
                'prepared_by_position' => $term?->position?->position_name,
                'created_by'           => Auth::id(),
                'officer_term_id'      => $term?->id,
            ]);
            $this->saveItems($proposal, $items);

            return $proposal;
        });

        $this->log($request, 'Created activity proposal "'.$proposal->title.'"');

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposal saved.');
    }

    public function show(ActivityProposal $proposal)
    {
        $proposal->load(['items', 'creator', 'copiedFrom']);

        return view('Admin.proposals.show', [
            'layout'      => $this->layout(),
            'proposal'    => $proposal,
            'canChange'   => $proposal->canBeChangedBy(Auth::user()),
            'letterheads' => Letterhead::orderBy('name')->get(['id', 'name']),
            // The letterhead picked for the last PDF, so it's one click next time.
            'letterheadId' => session('proposal_letterhead_id'),
        ]);
    }

    /**
     * The proposal as the SG prints it, on the chosen letterhead, opened in
     * the browser to print or save. Nothing is filed: the proposal itself is
     * the record, and changing it changes the next PDF.
     */
    public function pdf(Request $request, ActivityProposal $proposal)
    {
        $data = $request->validate(['letterhead' => ['nullable', 'integer', 'exists:letterheads,id']]);

        $letterhead = ! empty($data['letterhead']) ? Letterhead::find($data['letterhead']) : null;
        session(['proposal_letterhead_id' => $letterhead?->id]);

        $proposal->load('items');

        $pdf = Pdf::loadView('Admin.proposals.pdf', [
            'proposal'   => $proposal,
            'letterhead' => $letterhead,
            'borderBox'  => $letterhead?->borderCoverBox(Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT),
        ])->setPaper([0, 0, Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT]);

        return $pdf->stream(Str::slug($proposal->title ?: 'activity').'-proposal.pdf');
    }

    public function edit(ActivityProposal $proposal)
    {
        $this->authorizeChange($proposal);

        return view('Admin.proposals.form', $this->formData($proposal->load('items')));
    }

    public function update(Request $request, ActivityProposal $proposal)
    {
        $this->authorizeChange($proposal);

        [$fields, $items] = $this->validated($request);

        DB::transaction(function () use ($proposal, $fields, $items) {
            // A new date can move it to another school year; no date keeps
            // the one it was filed under.
            $fields['school_year'] = $fields['starts_on']
                ? SchoolYear::of(Carbon::parse($fields['starts_on']))
                : $proposal->school_year;

            $proposal->update($fields);
            $proposal->items()->delete();
            $this->saveItems($proposal, $items);
        });

        $this->log($request, 'Updated activity proposal "'.$proposal->title.'"');

        return redirect()->route('proposals.show', $proposal)->with('success', 'Changes saved.');
    }

    /**
     * A new draft with the same content, owned by whoever copied it - the
     * usual start for a yearly activity. Dates are left blank on purpose,
     * since last year's would be wrong.
     */
    public function copy(Request $request, ActivityProposal $proposal)
    {
        $proposal->load('items');
        $term = OfficerTerm::activeFor(Auth::id());

        $copy = DB::transaction(function () use ($proposal, $term) {
            $copy = ActivityProposal::create([
                'title'                => $proposal->title,
                'theme'                => $proposal->theme,
                'proponent'            => $proposal->proponent,
                'rationale'            => $proposal->rationale,
                'objectives_intro'     => $proposal->objectives_intro,
                'objectives'           => $proposal->objectives,
                'methods'              => $proposal->methods,
                'schedule'             => $proposal->schedule,
                'master_of_ceremony'   => $proposal->master_of_ceremony,
                'signatories'          => $proposal->signatories,
                'school_year'          => $term?->school_year ?? SchoolYear::current(),
                'prepared_by_position' => $term?->position?->position_name,
                'created_by'           => Auth::id(),
                'officer_term_id'      => $term?->id,
                'copied_from_id'       => $proposal->id,
            ]);
            $this->saveItems($copy, $proposal->items->map(fn ($item) => $item->only(['category', 'particular', 'quantity', 'unit', 'unit_price']))->all());

            return $copy;
        });

        $this->log($request, 'Copied activity proposal "'.$proposal->title.'"'.($proposal->school_year ? ' ('.$proposal->school_year.')' : ''));

        $from = $proposal->school_year ? 'the '.$proposal->school_year.' proposal' : 'the old proposal';

        return redirect()->route('proposals.edit', $copy)
            ->with('success', 'Copied from '.$from.'. Set the dates and check the names and prices.');
    }

    public function destroy(Request $request, ActivityProposal $proposal)
    {
        $this->authorizeChange($proposal);

        $title = $proposal->title;
        $proposal->delete();

        $this->log($request, 'Deleted activity proposal "'.$title.'"');

        return redirect()->route('proposals.index')->with('success', 'Proposal "'.$title.'" deleted.');
    }

    private function authorizeChange(ActivityProposal $proposal): void
    {
        abort_unless($proposal->canBeChangedBy(Auth::user()), 403, 'Only the officer who made this proposal or the Super Admin can change it.');
    }

    /**
     * The proposal's own fields and its budget rows, with blank rows left
     * out (the form always offers a few empty ones). A budget row with
     * anything in it needs a particular, a quantity and a unit price.
     *
     * @return array{0: array, 1: array<int, array>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'theme'                => ['nullable', 'string', 'max:255'],
            'starts_on'            => ['nullable', 'date'],
            'ends_on'              => ['nullable', 'date'],
            'proponent'            => ['nullable', 'string', 'max:255'],
            'rationale'            => ['nullable', 'string', 'max:20000'],
            'objectives_intro'     => ['nullable', 'string', 'max:10000'],
            'objectives'           => ['nullable', 'array', 'max:30'],
            'objectives.*'         => ['nullable', 'string', 'max:500'],
            'methods'              => ['nullable', 'string', 'max:20000'],
            'schedule'             => ['nullable', 'array', 'max:80'],
            'schedule.*.time'      => ['nullable', 'string', 'max:60'],
            'schedule.*.activity'  => ['nullable', 'string', 'max:2000'],
            'schedule.*.personnel' => ['nullable', 'string', 'max:500'],
            'master_of_ceremony'   => ['nullable', 'string', 'max:255'],
            'signatories'          => ['nullable', 'array'],
            'signatories.*'        => ['nullable', 'array', 'max:10'],
            'signatories.*.*.name'     => ['nullable', 'string', 'max:120'],
            'signatories.*.*.position' => ['nullable', 'string', 'max:150'],
            'items'                => ['nullable', 'array', 'max:150'],
            'items.*.category'     => ['nullable', 'string', 'max:100'],
            'items.*.particular'   => ['nullable', 'string', 'max:255'],
            'items.*.quantity'     => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.unit'         => ['nullable', 'string', 'max:30'],
            'items.*.unit_price'   => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'items.*.quantity.numeric'   => 'Each Quantity in the budget must be a number.',
            'items.*.unit_price.numeric' => 'Each Unit Price in the budget must be a number.',
        ]);

        $blank = fn ($value) => trim((string) ($value ?? '')) === '';

        if (! empty($data['starts_on']) && ! empty($data['ends_on'])
            && Carbon::parse($data['ends_on'])->lt(Carbon::parse($data['starts_on']))) {
            throw ValidationException::withMessages(['ends_on' => 'The end date can\'t be before the start date.']);
        }

        $objectives = collect($data['objectives'] ?? [])
            ->map(fn ($o) => trim((string) $o))->reject(fn ($o) => $o === '')->values()->all();

        $schedule = collect($data['schedule'] ?? [])
            ->map(fn ($row) => [
                'time'      => trim((string) ($row['time'] ?? '')),
                'activity'  => trim((string) ($row['activity'] ?? '')),
                'personnel' => trim((string) ($row['personnel'] ?? '')),
            ])
            ->reject(fn ($row) => $row['time'] === '' && $row['activity'] === '' && $row['personnel'] === '')
            ->values()->all();

        // Only the five groups of the format, each without its empty rows.
        $signatories = [];
        foreach (array_keys(ActivityProposal::SIGNATORY_GROUPS) as $group) {
            $signatories[$group] = collect($data['signatories'][$group] ?? [])
                ->map(fn ($p) => ['name' => trim((string) ($p['name'] ?? '')), 'position' => trim((string) ($p['position'] ?? ''))])
                ->reject(fn ($p) => $p['name'] === '' && $p['position'] === '')
                ->values()->all();
        }

        // Errors use the row's own key from the form, so they land next to
        // the right inputs when the form comes back; the message counts
        // rows the way the officer sees them.
        $items = [];
        $errors = [];
        $n = 0;
        foreach ($data['items'] ?? [] as $key => $row) {
            $n++;
            if (collect(['category', 'particular', 'quantity', 'unit', 'unit_price'])->every(fn ($k) => $blank($row[$k] ?? null))) {
                continue;
            }
            if ($blank($row['particular'] ?? null)) {
                $errors["items.$key.particular"] = "Budget row $n needs a Particular.";
            }
            if ($blank($row['quantity'] ?? null)) {
                $errors["items.$key.quantity"] = "Budget row $n needs a Quantity.";
            }
            if ($blank($row['unit_price'] ?? null)) {
                $errors["items.$key.unit_price"] = "Budget row $n needs a Unit Price.";
            }
            $items[] = [
                'category'   => $blank($row['category'] ?? null) ? null : trim($row['category']),
                'particular' => trim((string) ($row['particular'] ?? '')),
                'quantity'   => $row['quantity'] ?? 0,
                'unit'       => $blank($row['unit'] ?? null) ? null : trim($row['unit']),
                'unit_price' => $row['unit_price'] ?? 0,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $text = fn (string $key) => $blank($data[$key] ?? null) ? null : trim($data[$key]);

        $fields = [
            'title'              => trim($data['title']),
            'theme'              => $text('theme'),
            'starts_on'          => $data['starts_on'] ?? null,
            'ends_on'            => $data['ends_on'] ?? null,
            'proponent'          => $text('proponent'),
            'rationale'          => $text('rationale'),
            'objectives_intro'   => $text('objectives_intro'),
            'objectives'         => $objectives,
            'methods'            => $text('methods'),
            'schedule'           => $schedule,
            'master_of_ceremony' => $text('master_of_ceremony'),
            'signatories'        => $signatories,
        ];

        return [$fields, $items];
    }

    private function saveItems(ActivityProposal $proposal, array $items): void
    {
        foreach (array_values($items) as $i => $item) {
            $proposal->items()->create($item + ['position' => $i]);
        }
    }

    /** The activity's own school year when it has a date, else the maker's. */
    private function schoolYearFor(?string $startsOn, ?OfficerTerm $term): string
    {
        if ($startsOn) {
            return SchoolYear::of(Carbon::parse($startsOn));
        }

        return $term?->school_year ?? SchoolYear::current();
    }

    /** What the form needs, for a new proposal or an existing one. */
    private function formData(ActivityProposal $proposal): array
    {
        return [
            'layout'     => $this->layout(),
            'proposal'   => $proposal,
            // Suggestions only: the SG's own category and unit names, taken
            // from what earlier proposals used, never a fixed list.
            'categories' => ActivityProposalItem::whereNotNull('category')->distinct()->orderBy('category')->limit(50)->pluck('category'),
            'units'      => ActivityProposalItem::whereNotNull('unit')->distinct()->orderBy('unit')->limit(50)->pluck('unit'),
        ];
    }

    private function layout(): string
    {
        return Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';
    }

    private function log(Request $request, string $activity): void
    {
        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => $activity,
            'ip_address' => $request->ip(),
        ]);
    }
}
