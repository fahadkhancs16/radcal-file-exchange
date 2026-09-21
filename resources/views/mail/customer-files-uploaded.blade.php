<x-mail::message>
# {{ $exchange->customer_name }} sent files

**Reference:** {{ $exchange->code }}
**Company:** {{ $exchange->company ?: '—' }}
**Email:** {{ $exchange->email }}

**Files this time:**
@foreach ($files as $file)
- {{ $file->original_filename }} ({{ $file->humanSize() }})
@endforeach

@if (filled($explanation))
<x-mail::panel>
**Note from the customer:**
{{ $explanation }}
</x-mail::panel>

@endif
<x-mail::button :url="route('filament.admin.resources.exchanges.view', $exchange)">
Open exchange {{ $exchange->code }}
</x-mail::button>

@if (filled($explanation))
If it needs a response, reach out to the customer directly using the details above.
@else
This is not a support request — the customer hasn't told us why these files were sent. If it needs a response, reach out to them directly using the details above.
@endif

Radcal File Exchange
</x-mail::message>
