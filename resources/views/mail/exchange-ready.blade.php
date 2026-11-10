<x-mail::message>
# Your files reached Radcal

Thank you, {{ $exchange->customer_name }}. Your upload created a secure File Exchange.

**Reference number:** {{ $exchange->code }}
**Password:** `{{ $password }}`

<x-mail::button :url="$url">
Open your File Exchange
</x-mail::button>

Thanks,<br>
Radcal File Exchange
</x-mail::message>
