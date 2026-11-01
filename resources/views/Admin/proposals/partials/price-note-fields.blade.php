{{-- The officer's reason for an unusual price, typed in the Price check box
     under the budget ("Price is correct") and kept with its row. It is saved
     only for the price it was written for (price_note_for). --}}
<input type="hidden" name="items[{{ $key }}][price_note]" value="{{ $row['price_note'] ?? '' }}" data-price-note>
<input type="hidden" name="items[{{ $key }}][price_note_for]" value="{{ $row['price_note_for'] ?? '' }}" data-price-note-for>
