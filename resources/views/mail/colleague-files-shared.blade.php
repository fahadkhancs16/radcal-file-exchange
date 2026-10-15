<x-mail::message>
# {{ $sender }} shared files with you

**Reference:** {{ $recipientExchange?->code ?? $exchange->code }}

**Files added:**
@foreach ($files as $file)
- {{ $file->original_filename }} ({{ $file->humanSize() }})
@endforeach

@if (filled($explanation))
<x-mail::panel>
**Note from {{ $sender }}:**
{{ $explanation }}
</x-mail::panel>

@endif
<x-mail::button :url="route('exchange.landing', $recipientExchange ?? $exchange)">
Open the File Exchange
</x-mail::button>

<x-mail::panel>
This exchange and every file in it will be permanently deleted on **{{ $exchange->expires_at->format('F j, Y') }}**. Download anything you need to keep before that date.
</x-mail::panel>

Thanks,<br>
Radcal File Exchange
</x-mail::message>
