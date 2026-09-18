<x-mail::message>
# {{ $exchange->customer_name }} sent files

**Reference:** {{ $exchange->code }}
**Company:** {{ $exchange->company ?: '—' }}
**Email:** {{ $exchange->email }}

@if ($exchange->description)
**Description given at upload:** {{ $exchange->description }}

@endif
**Files this time:**
@foreach ($files as $file)
- {{ $file->original_filename }} ({{ $file->humanSize() }})
@endforeach

<x-mail::button :url="route('filament.admin.resources.exchanges.view', $exchange)">
Open exchange {{ $exchange->code }}
</x-mail::button>

This is not a support request — the customer hasn't told us why these files were sent. If it needs a response, reach out to them directly using the details above.

Radcal File Exchange
</x-mail::message>
