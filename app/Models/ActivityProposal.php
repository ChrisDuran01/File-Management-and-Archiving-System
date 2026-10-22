<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Detailed Activity Proposal, laid out like the SG's own (see the
 * activity_proposals migrations). Any officer can read and copy one; only
 * its maker or the Super Admin can change or delete it.
 */
class ActivityProposal extends Model
{
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
        ];
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
