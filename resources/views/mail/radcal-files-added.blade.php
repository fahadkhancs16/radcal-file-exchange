<x-mail::message>
# Radcal added files to your exchange

**Reference:** {{ $exchange->code }}

**Files added:**
@foreach ($files as $file)
- {{ $file->original_filename }} ({{ $file->humanSize() }})
@endforeach

@if (filled($explanation))
<x-mail::panel>
**Note from Radcal:**
{{ $explanation }}
</x-mail::panel>

@endif
<x-mail::button :url="route('exchange.landing', $exchange)">
Open your File Exchange
</x-mail::button>


Thanks,<br>
Radcal File Exchange
</x-mail::message>
