{{--
    Name + position rows for one signature group (or "Thru"). Add and
    Remove are handled by the page script through data-add-row /
    data-remove-row, the same way as on the Activity Proposal form.

        @include('Admin.templates.forms.people', [
            'id'     => 'tpl1-sig-noted',                    // unique on the page
            'name'   => 'fields[signatories][noted]',
            'label'  => 'Noted',
            'people' => [['name' => '', 'position' => 'Adviser, Student Government']],
        ])
--}}
<div class="sig-group">
    <h4 id="{{ $id }}-title">{{ $label }}</h4>
    <div class="table-scroll">
        <table class="grid-table sig-table" aria-labelledby="{{ $id }}-title">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Position</th>
                    <th scope="col" style="width:48px;"><span class="sr-only">Remove</span></th>
                </tr>
            </thead>
            <tbody id="{{ $id }}List">
                @foreach ($people as $person)
                    <tr data-row>
                        <td><input name="{{ $name }}[{{ $loop->index }}][name]" class="prop-input" maxlength="120" value="{{ $person['name'] }}" data-label="{{ $label }}, name" aria-label="{{ $label }}, name {{ $loop->iteration }}"></td>
                        <td><textarea name="{{ $name }}[{{ $loop->index }}][position]" class="prop-input cell-text" rows="1" maxlength="300" data-label="{{ $label }}, position" aria-label="{{ $label }}, position {{ $loop->iteration }}">{{ $person['position'] }}</textarea></td>
                        <td><button type="button" class="row-remove" data-remove-row data-label="Remove {{ $label }} row" aria-label="Remove {{ $label }} row {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <template id="{{ $id }}Tpl">
        <tr data-row>
            <td><input name="{{ $name }}[__KEY__][name]" class="prop-input" maxlength="120" data-label="{{ $label }}, name" aria-label="{{ $label }}, name"></td>
            <td><textarea name="{{ $name }}[__KEY__][position]" class="prop-input cell-text" rows="1" maxlength="300" data-label="{{ $label }}, position" aria-label="{{ $label }}, position"></textarea></td>
            <td><button type="button" class="row-remove" data-remove-row data-label="Remove {{ $label }} row" aria-label="Remove {{ $label }} row"><i class="fas fa-times" aria-hidden="true"></i></button></td>
        </tr>
    </template>
    <button type="button" class="btn-flat ghost btn-sm" data-add-row="{{ $id }}"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add to {{ $label }}</button>
</div>
