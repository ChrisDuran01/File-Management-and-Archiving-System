<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of a proposal's Budgetary Requirements. The Total Price is never
 * stored: it is always Quantity x Unit Price, so it can't drift from them.
 * price_note is the officer's reason when the price check
 * (App\Services\PriceCheck) finds the Unit Price unusual but it is right.
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
        'price_note',
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
