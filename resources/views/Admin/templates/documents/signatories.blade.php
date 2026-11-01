{{--
    Signature blocks of a template document: each group's label, then its
    people two to a row, an odd one out on a row of its own. With $signed,
    "(SGD)" is printed above the names, as on the copy posted after signing;
    otherwise there is room to sign.

        @include('Admin.templates.documents.signatories', [
            'groups' => $doc::signatoryGroups($fields),   // key => [label|null, people]
            'signed' => $fields['signed'],
            'align'  => 'center',                          // or 'left'
            'strong' => ['certified'],                     // labels printed bold, in capitals
        ])
--}}
@foreach ($groups as $key => $group)
    <div class="sign-group {{ ($align ?? 'center') === 'left' ? 'sign-left' : '' }}">
        @if ($group['label'] !== null)
            <p class="sign-label {{ in_array($key, $strong ?? [], true) ? 'strong' : '' }}">{{ $group['label'] }}:</p>
        @endif
        <table class="signers">
            @foreach (array_chunk($group['people'], 2) as $pair)
                <tr>
                    @foreach ($pair as $person)
                        <td @if (count($pair) === 1) colspan="2" @endif>
                            @if ($signed)
                                <div>(SGD)</div>
                            @else
                                <div class="signer-space"></div>
                            @endif
                            <div class="signer-name">{{ $person['name'] !== '' ? $person['name'] : '______________________' }}</div>
                            @foreach (\App\Support\Documents\TemplateDocument::splitLines($person['position']) as $line)
                                <div class="signer-position">{{ $line }}</div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
