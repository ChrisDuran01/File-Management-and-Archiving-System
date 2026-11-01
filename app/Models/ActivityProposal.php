<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A Detailed Activity Proposal, laid out like the SG's own (see the
 * activity_proposals migrations). Any officer can read and copy one; only
 * its maker or the Super Admin can change it, set its status or delete it.
 * Once approved its content is locked to match the signed paper, until the
 * Super Admin reopens it. Deleting it sends it to the Trash.
 */
class ActivityProposal extends Model
{
    use SoftDeletes;

    /** Where a proposal is, in the order it usually goes. */
    public const STATUSES = [
        'draft'        => 'Draft',
        'for_signing'  => 'For signing',
        'approved'     => 'Approved',
        'not_approved' => 'Not approved',
        'done'         => 'Done',
        'cancelled'    => 'Cancelled',
    ];

    /** What an approved (locked) proposal can still become. */
    private const AFTER_APPROVAL = ['approved', 'done', 'cancelled'];

    /** The statuses the student page shows, when the switch is on. */
    private const SHOWN_TO_STUDENTS = ['approved', 'done', 'cancelled'];

    /** The folder the scans of signed proposals go into. */
    public const SIGNED_COPIES_FOLDER = 'Activity Proposals';

    /** The signature groups of the SG's proposal, in the order they are printed. */
    public const SIGNATORY_GROUPS = [
        'prepared'     => 'Prepared by',
        'noted'        => 'Noted',
        'budget'       => 'Budget Available',
        'recommending' => 'Recommending Approval',
        'approved'     => 'Approved',
    ];

    protected $fillable = [
        'title',
        'theme',
        'starts_on',
        'ends_on',
        'proponent',
        'rationale',
        'objectives_intro',
        'objectives',
        'methods',
        'schedule',
        'master_of_ceremony',
        'signatories',
        'status',
        'approved_at',
        'signed_file_id',
        'is_public',
        'school_year',
        'prepared_by_position',
        'created_by',
        'officer_term_id',
        'copied_from_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on'   => 'date',
            'ends_on'     => 'date',
            'objectives'  => 'array',
            'schedule'    => 'array',
            'signatories' => 'array',
            'approved_at' => 'datetime',
            'is_public'   => 'boolean',
        ];
    }

    protected $attributes = [
        'status'    => 'draft',
        'is_public' => false,
    ];

    /**
     * What the student page shows: switched on, and approved (or held, or
     * called off after approval). Drafts and proposals still being signed
     * or turned down never show, whatever the switch says.
     */
    public function scopeOnStudentPage($query)
    {
        return $query->where('is_public', true)->whereIn('status', self::SHOWN_TO_STUDENTS);
    }

    public function isOnStudentPage(): bool
    {
        return $this->is_public && in_array($this->status, self::SHOWN_TO_STUDENTS, true);
    }

    /** "leadership-training", the readable end of its student page address. */
    public function slug(): string
    {
        return Str::slug($this->title) ?: 'activity';
    }

    /**
     * The tag students see: Done or Cancelled from the status; for an
     * approved activity, Upcoming before it starts and Happening now until
     * its last day; none once its date passed without being marked Done.
     */
    public function studentTag(): ?string
    {
        return match (true) {
            $this->status === 'done'      => 'Done',
            $this->status === 'cancelled' => 'Cancelled',
            ! $this->starts_on || $this->starts_on->gte(today()) => 'Upcoming',
            $this->ends_on && $this->ends_on->gte(today()) => 'Happening now',
            default => null,
        };
    }

    /**
     * Budget per category, biggest first, for the student page: what each
     * kind of spending adds up to, without the line items.
     *
     * @return array<string, float>
     */
    public function budgetByCategory(): array
    {
        return $this->items
            ->groupBy(fn (ActivityProposalItem $item) => $item->category ?: 'Other')
            ->map(fn ($items) => round($items->sum(fn (ActivityProposalItem $item) => $item->totalPrice()), 2))
            ->sortDesc()
            ->all();
    }

    public function items()
    {
        return $this->hasMany(ActivityProposalItem::class)->orderBy('position')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function copiedFrom()
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    /** The scan of the signed paper; null while none is attached or it is in the Trash. */
    public function signedFile()
    {
        return $this->belongsTo(File::class, 'signed_file_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Approved once, so the content stays as it was signed. Only the Super Admin can reopen it. */
    public function isLocked(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * The statuses this proposal can be set to now. Before approval, any but
     * Done (an activity is approved before it is held); after, only the ones
     * that keep it approved - going back means reopening it.
     *
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        $keys = $this->isLocked()
            ? self::AFTER_APPROVAL
            : array_values(array_diff(array_keys(self::STATUSES), ['done']));

        return array_intersect_key(self::STATUSES, array_flip($keys));
    }

    /** Sets the status; the first time it becomes Approved, the content is locked. */
    public function moveTo(string $status): void
    {
        $this->status = $status;
        if ($status === 'approved' && ! $this->isLocked()) {
            $this->approved_at = now();
        }
        $this->save();
    }

    /** Super Admin only (checked by the caller): unlocks it and makes it a draft again. */
    public function reopen(): void
    {
        $this->forceFill(['status' => 'draft', 'approved_at' => null])->save();
    }

    public function budgetTotal(): float
    {
        return round($this->items->sum(fn (ActivityProposalItem $item) => $item->totalPrice()), 2);
    }

    public function canBeChangedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ($this->created_by && (int) $this->created_by === (int) $user->id) || Folder::isSuperAdmin($user);
    }

    /**
     * For the Trash: any officer can open a proposal, so any officer can
     * restore a deleted one, the same as a file in an open folder.
     */
    public function isAccessibleBy(User $user): bool
    {
        return true;
    }

    public function restoreFromTrash(): void
    {
        $this->restore();
    }

    /** As the SG writes it: "October 22, 2026", "November 6–8, 2026", "November 30 – December 2, 2026". */
    public function dateLabel(): string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if (! $start) {
            return 'No date yet';
        }
        if (! $end || $end->isSameDay($start)) {
            return $start->format('F j, Y');
        }
        if ($start->isSameMonth($end)) {
            return $start->format('F j').'–'.$end->format('j, Y');
        }
        if ($start->year === $end->year) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j, Y').' – '.$end->format('F j, Y');
    }

    /** Who wrote it, as the list and the proposal page show it. */
    public function preparedByLabel(): string
    {
        return $this->prepared_by_position ?: ($this->creator?->name ?? '—');
    }

    /**
     * Every signature group with its people, in print order; groups left
     * empty are dropped.
     *
     * @return array<string, array{label: string, people: array<int, array{name: string, position: string}>}>
     */
    public function signatoryGroups(): array
    {
        $groups = [];
        foreach (self::SIGNATORY_GROUPS as $key => $label) {
            $people = array_values(array_filter(
                $this->signatories[$key] ?? [],
                fn ($p) => trim($p['name'] ?? '') !== '' || trim($p['position'] ?? '') !== ''
            ));
            if ($people) {
                $groups[$key] = ['label' => $label, 'people' => $people];
            }
        }

        return $groups;
    }

    /**
     * What a new proposal starts with: the signatories of the last proposal
     * that has them (the same offices sign every proposal, so they are typed
     * once), else the positions from the SG's format with the names blank.
     */
    public static function defaultSignatories(): array
    {
        $last = self::whereNotNull('signatories')->latest('updated_at')->latest('id')->value('signatories');
        $last = is_string($last) ? json_decode($last, true) : $last;

        if (is_array($last) && collect($last)->flatten()->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
            return $last;
        }

        $blank = fn (string ...$positions) => array_map(fn ($position) => ['name' => '', 'position' => $position], $positions);

        return [
            'prepared'     => $blank('SG Secretary', 'SG Vice President', 'Student Regent/SG President'),
            'noted'        => $blank('SG Adviser', 'University Director for Student Affairs & Services', 'University Director for Instruction'),
            'budget'       => $blank('Budget Officer'),
            'recommending' => $blank('VP for Administration & Finance', 'VP for Academics, Research, & Extension'),
            'approved'     => $blank('University President'),
        ];
    }

    /** ₱15,000.00 - formatted by hand, XAMPP's PHP has no intl for Number::currency(). */
    public static function peso(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }
}
