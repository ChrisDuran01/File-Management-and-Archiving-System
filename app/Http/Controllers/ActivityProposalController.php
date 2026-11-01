<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\ActivityProposalItem;
use App\Models\File;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Models\OfficerTerm;
use App\Services\BudgetRules;
use App\Services\PriceCheck;
use App\Services\ProposalWordImport;
use App\Support\SchoolYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


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
        $status = array_key_exists($request->query('status'), ActivityProposal::STATUSES) ? $request->query('status') : '';

        $proposals = ActivityProposal::with(['items', 'creator', 'copiedFrom'])
            ->when($year !== 'all', fn ($q) => $q->where('school_year', $year))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
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
            'status'    => $status,
        ]);
    }

    public function create()
    {
        $proposal = new ActivityProposal([
            'proponent'   => 'Student Government',
            'signatories' => ActivityProposal::defaultSignatories(),
        ]);

        // Just read from a Word file (importRead): the form starts filled in.
        $import = session('proposal_import');
        if ($import) {
            $f = $import['fields'];
            $hasSigners = array_sum(array_map('count', $f['signatories'])) > 0;
            $proposal->fill([
                'title'              => $f['title'],
                'theme'              => $f['theme'],
                'starts_on'          => $f['starts_on'],
                'ends_on'            => $f['ends_on'],
                'proponent'          => $f['proponent'] ?? 'Student Government',
                'rationale'          => $f['rationale'],
                'objectives_intro'   => $f['objectives_intro'],
                'objectives'         => $f['objectives'],
                'methods'            => $f['methods'],
                'schedule'           => $f['schedule'],
                'master_of_ceremony' => $f['master_of_ceremony'],
                'signatories'        => $hasSigners ? $f['signatories'] : $proposal->signatories,
            ]);
        }

        return view('Admin.proposals.form', $this->formData($proposal) + ['import' => $import]);
    }

    /** Where an officer picks the Word file of a proposal written before the system. */
    public function importForm()
    {
        return view('Admin.proposals.import', ['layout' => $this->layout()]);
    }

   
    public function importRead(Request $request, ProposalWordImport $reader)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:docx'],
        ], [
            'file.required'   => 'Choose the Word file of the proposal.',
            'file.extensions' => 'Choose a Word file (.docx). For a .doc file, open it in Word and Save As .docx first.',
            'file.max'        => 'The Word file is too large (over 10 MB).',
        ]);

        try {
            $result = $reader->read($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('proposals.create')
            ->with('proposal_import', $result + ['filename' => mb_substr($request->file('file')->getClientOriginalName(), 0, 200)]);
    }

    public function store(Request $request)
    {
        [$fields, $items] = $this->validated($request);
        $importedFrom = $request->validate(['imported_from' => ['nullable', 'string', 'max:255']])['imported_from'] ?? null;

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

        $this->log($request, 'Created activity proposal "'.$proposal->title.'"'
            .($importedFrom ? ' (imported from Word file "'.$importedFrom.'")' : ''));

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposal saved.');
    }

    public function show(ActivityProposal $proposal)
    {
        $proposal->load(['items', 'creator', 'copiedFrom', 'signedFile']);
        $user = Auth::user();
        $canChange = $proposal->canBeChangedBy($user);

        
        $folder = $canChange ? $this->signedCopiesFolder(create: false) : null;
        $signedChoices = $folder && $folder->isAccessibleBy($user)
            ? File::where('folder_id', $folder->id)->whereKeyNot($proposal->signed_file_id ?? 0)
                ->latest('id')->limit(100)->get(['id', 'filename', 'created_at'])
            : collect();

        return view('Admin.proposals.show', [
            'layout'      => $this->layout(),
            'proposal'    => $proposal,
            'priceChecks' => PriceCheck::fromSettings()->forProposal($proposal),
            'canChange'   => $canChange,
            'canEdit'     => $canChange && ! $proposal->isLocked(),
            'isSuperAdmin' => Folder::isSuperAdmin($user),
            'signedChoices' => $signedChoices,
            'letterheads' => Letterhead::orderBy('name')->get(['id', 'name', 'is_default']),
            // The letterhead picked for the last PDF, so it's one click next
            // time; before any, the default one.
            'letterheadId' => session('proposal_letterhead_id') ?? Letterhead::defaultOne()?->id,
        ]);
    }

    
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

    
    public function budgetCheck(Request $request)
    {
        $data = $request->validate([
            'proposal'           => ['nullable', 'integer'],
            'starts_on'          => ['nullable', 'date'],
            'items'              => ['nullable', 'array', 'max:150'],
            'items.*.particular' => ['nullable', 'string', 'max:255'],
            'items.*.unit'       => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $proposal = ! empty($data['proposal']) ? ActivityProposal::find($data['proposal']) : null;
        
        $year = ! empty($data['starts_on'])
            ? PriceCheck::yearOf(null, $data['starts_on'])
            : PriceCheck::yearOf($proposal?->school_year);

        $items = $data['items'] ?? [];
        $prices = collect(PriceCheck::fromSettings()->check($items, $year, $proposal?->id))
            ->map(fn ($check) => $check ? array_intersect_key($check, array_flip(['state', 'count', 'proposals', 'summary', 'median'])) : null);

        $rules = new BudgetRules;

        return response()->json([
            'prices'      => (object) $prices->all(),
            'suggestions' => $rules->suggest(array_column($items, 'particular')),
            'approved'    => $rules->mine()['baskets'],
            'needed'      => BudgetRules::MIN_PROPOSALS,
        ]);
    }

    public function edit(ActivityProposal $proposal)
    {
        
        if ($proposal->isLocked()) {
            return redirect()->route('proposals.show', $proposal)->with('error', 'This proposal is approved, so it can\'t be edited. '
                .'To print it, pick the letterhead and click Open PDF. To change it, Copy it into a new proposal'
                .(Folder::isSuperAdmin(Auth::user()) ? ', or click Reopen for changes.' : ', or ask the Super Admin to reopen it.'));
        }

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

    /** Into the Trash, like a file: restorable until it is purged. */
    public function destroy(Request $request, ActivityProposal $proposal)
    {
        $this->authorizeChange($proposal);

        $proposal->delete();

        $this->log($request, 'Moved activity proposal "'.$proposal->title.'" to Trash');

        return redirect()->route('proposals.index')
            ->with('success', 'Proposal "'.$proposal->title.'" moved to the Trash. It can be restored from there.');
    }

    public function status(Request $request, ActivityProposal $proposal)
    {
        abort_unless($proposal->canBeChangedBy(Auth::user()), 403, 'Only the officer who made this proposal or the Super Admin can change its status.');

        $options = $proposal->statusOptions();
        $data = $request->validate(['status' => ['required', Rule::in(array_keys($options))]], [
            'status.in' => $proposal->isLocked()
                ? 'An approved proposal can only be set to '.implode(', ', $options).'. The Super Admin can reopen it.'
                : 'A proposal is set to Done only after it is approved.',
        ]);

        if ($data['status'] === $proposal->status) {
            return redirect()->route('proposals.show', $proposal);
        }

        $wasLocked = $proposal->isLocked();
        $proposal->moveTo($data['status']);

        $this->log($request, 'Updated status of activity proposal "'.$proposal->title.'" to '.$proposal->statusLabel());

        return redirect()->route('proposals.show', $proposal)->with('success', 'Status set to '.$proposal->statusLabel().'.'
            .(! $wasLocked && $proposal->isLocked() ? ' Its content is now locked so it stays as it was signed.' : ''));
    }

    /**
     * The "Show on student page" switch. It can be set at any status; the
     * student page still waits until the proposal is approved.
     */
    public function publish(Request $request, ActivityProposal $proposal)
    {
        abort_unless($proposal->canBeChangedBy(Auth::user()), 403, 'Only the officer who made this proposal or the Super Admin can show it on the student page.');

        $proposal->is_public = $request->boolean('is_public');
        $proposal->save();

        $this->log($request, 'Updated activity proposal "'.$proposal->title.'": '
            .($proposal->is_public ? 'shown on the student page' : 'hidden from the student page'));

        return redirect()->route('proposals.show', $proposal)->with('success', match (true) {
            ! $proposal->is_public => 'Hidden from the student page.',
            $proposal->isOnStudentPage() => 'Shown on the student page.',
            default => 'It will show on the student page once it is approved.',
        });
    }

    /** Unlocks an approved proposal, e.g. when the signed version must be redone. */
    public function reopen(Request $request, ActivityProposal $proposal)
    {
        abort_unless(Folder::isSuperAdmin(Auth::user()), 403, 'Only the Super Admin can reopen an approved proposal.');

        if (! $proposal->isLocked()) {
            return redirect()->route('proposals.show', $proposal);
        }

        $proposal->reopen();

        $this->log($request, 'Updated status of activity proposal "'.$proposal->title.'" to Draft (reopened for changes)');

        return redirect()->route('proposals.show', $proposal)
            ->with('success', 'Reopened as a draft, so it can be changed. Set it to Approved again once the new version is signed.');
    }

   
    public function attachSigned(Request $request, ActivityProposal $proposal)
    {
        abort_unless($proposal->canBeChangedBy(Auth::user()), 403, 'Only the officer who made this proposal or the Super Admin can attach its signed copy.');

        $request->validate([
            'files'   => ['required_without:file_id', 'array', 'max:1'],
            'files.*' => ['file', 'max:1048576', 'mimes:pdf,jpg,jpeg,png,webp'],
            'file_id' => ['nullable', 'integer'],
        ], [
            'files.required_without' => 'Choose the scan of the signed proposal.',
            'files.*.mimes'          => 'The signed copy must be a PDF or a photo (JPG, PNG or WebP).',
        ]);

        $folder = $this->signedCopiesFolder(create: $request->hasFile('files'));
        if ($folder && ! $folder->isAccessibleBy(Auth::user())) {
            return back()->with('error', 'The "'.$folder->name.'" folder is restricted. Ask its owner or the Super Admin for access.');
        }

        if ($request->hasFile('files')) {
            $file = $this->uploadInto($folder, $request);
            if (! $file) {
                return back()->with('error', session('error') ?: 'The signed copy could not be saved. Try again.');
            }
        } else {
            $file = $folder ? File::where('folder_id', $folder->id)->find($request->integer('file_id')) : null;
            if (! $file) {
                return back()->with('error', 'That file is not in the "'.ActivityProposal::SIGNED_COPIES_FOLDER.'" folder.');
            }
        }

        $wasLocked = $proposal->isLocked();
        $proposal->signed_file_id = $file->id;
        $wasLocked ? $proposal->save() : $proposal->moveTo('approved');

        $this->log($request, 'Updated activity proposal "'.$proposal->title.'": signed copy "'.$file->filename.'" attached'
            .($wasLocked ? '' : ', status Approved'));

        return redirect()->route('proposals.show', $proposal)->with('success', 'Signed copy attached.'
            .($wasLocked ? '' : ' The proposal is now Approved, and its content is locked so it stays as it was signed.'));
    }

    private function authorizeChange(ActivityProposal $proposal): void
    {
        abort_unless($proposal->canBeChangedBy(Auth::user()), 403, 'Only the officer who made this proposal or the Super Admin can change it.');
        abort_if($proposal->isLocked(), 403, 'This proposal is approved, so it stays as it was signed. Copy it to start a new one, or ask the Super Admin to reopen it.');
    }

    
    private function signedCopiesFolder(bool $create): ?Folder
    {
        $folder = Folder::where('is_archived', false)->where('name', ActivityProposal::SIGNED_COPIES_FOLDER)->oldest('id')->first();

        if ($folder || ! $create) {
            return $folder;
        }

        $term = OfficerTerm::activeFor(Auth::id());
        $folder = Folder::create([
            'name'            => ActivityProposal::SIGNED_COPIES_FOLDER,
            'created_by'      => Auth::id(),
            'officer_term_id' => $term?->id,
            'school_year'     => $term?->school_year,
        ]);

        $this->log(request(), 'Added folder: '.$folder->name.' (for signed activity proposals)');

        return $folder;
    }

    private function uploadInto(Folder $folder, Request $request): ?File
    {
        $lastId = (int) File::withTrashed()->max('id');

        $request->merge(['folder_id' => $folder->id]);
        app()->call([app(FileController::class), 'store'], ['request' => $request]);

        return File::where('folder_id', $folder->id)->where('id', '>', $lastId)->latest('id')->first();
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
            'items.*.price_note'     => ['nullable', 'string', 'max:300'],
            'items.*.price_note_for' => ['nullable', 'numeric'],
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
                // "Price is correct": the reason holds only for the price it
                // was written for; a changed price needs a new look.
                'price_note' => ! $blank($row['price_note'] ?? null) && ! $blank($row['price_note_for'] ?? null)
                    && round((float) $row['price_note_for'], 2) === round((float) ($row['unit_price'] ?? 0), 2)
                    ? trim($row['price_note']) : null,
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
