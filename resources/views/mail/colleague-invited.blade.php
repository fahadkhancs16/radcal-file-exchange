<x-mail::message>
# {{ $invitedBy }} shared a File Exchange with you

You can now view, download and add files in this secure Radcal File Exchange.

**Reference number:** {{ $exchange->code }}
**Your password:** `{{ $password }}`

@if (filled($message))
<x-mail::panel>
**Note from {{ $invitedBy }}:**
{{ $message }}
</x-mail::panel>

@endif
<x-mail::button :url="route('exchange.landing', $exchange)">
Open the File Exchange
</x-mail::button>

<x-mail::panel>
This exchange and every file in it will be permanently deleted on **{{ $exchange->expires_at->format('F j, Y') }}**. Download anything you need to keep before that date.
</x-mail::panel>

Thanks,<br>
Radcal File Exchange
</x-mail::message>
