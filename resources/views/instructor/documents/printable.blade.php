<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->requirement->name }} — {{ $document->internship->student->user->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; color: #1a1a1a; }
        h1 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .meta { color: #555; font-size: 0.85rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        td { padding: 0.6rem 0.4rem; border-bottom: 1px solid #ddd; vertical-align: top; }
        td.label { width: 35%; color: #555; font-size: 0.85rem; }
        td.value { font-weight: 600; }
        .signature-line { margin-top: 3rem; border-top: 1px solid #333; width: 250px; padding-top: 0.3rem; font-size: 0.8rem; color: #555; }
        @media print {
            .no-print { display: none; }
        }
    </style>
    @include('partials.a4-page')
</head>
<body onload="window.print()">
    <button class="no-print" onclick="window.print()">Print</button>

    <h1>{{ $document->requirement->name }}</h1>
    <div class="meta">
        {{ $document->internship->student->user->name }} &middot; {{ $document->internship->student->user->id_number }}
        &middot; Printed {{ now()->format('M d, Y') }}
    </div>

    @if ($document->requirement->is_repeating)
        <table>
            <thead>
                <tr>
                    <td class="label"><strong>{{ $document->requirement->entry_cadence === 'weekly' ? 'Week' : 'Date' }}</strong></td>
                    @foreach ($document->requirement->fieldDefinitions as $field)
                        <td><strong>{{ $field->label }}</strong></td>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($document->entries as $entry)
                    <tr>
                        <td class="label">
                            {{ $entry->entry_date->format('M d, Y') }}
                            @if ($entry->entry_end_date) &ndash; {{ $entry->entry_end_date->format('M d, Y') }} @endif
                        </td>
                        @foreach ($document->requirement->fieldDefinitions as $field)
                            <td class="value" style="font-weight: 400;">{{ $entry->value($field->field_key) ?: '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ $document->requirement->fieldDefinitions->count() + 1 }}">No entries logged.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <table>
            @foreach ($document->requirement->fieldDefinitions as $field)
                <tr>
                    <td class="label">{{ $field->label }}</td>
                    <td class="value">{{ $document->fieldValue($field->field_key) ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="width:250px; text-align:center; font-weight:bold; margin-top:3rem;">{{ $document->internship?->student?->user?->name }}</div>
    <div class="signature-line" style="margin-top:2px;">Signature over Printed Name</div>
</body>
</html>
