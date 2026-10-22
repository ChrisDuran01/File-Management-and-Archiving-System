<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of a proposal's Budgetary Requirements. The Total Price is never
 * stored: it is always Quantity x Unit Price, so it can't drift from them.
 */
class ActivityProposalItem extends Model
{
    protected $fillable = [
        'activity_proposal_id',
        'position',
        'category',
        'particular',
        'quantity',
        'unit',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }

    public function totalPrice(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
