<x-mail::message>
# Your files reached Radcal

Thank you, {{ $exchange->customer_name }}. Your upload created a secure File Exchange.

**Reference number:** {{ $exchange->code }}
**Password:** `{{ $password }}`

Use the button below to return to the exchange while it stays active. You can add more files, replace a file by uploading one with the same name, or remove files you uploaded. You will not need to verify your email again.

<x-mail::button :url="$url">
Open your File Exchange
</x-mail::button>

<x-mail::panel>
This exchange and every file in it will be permanently deleted on **{{ $exchange->expires_at->format('F j, Y') }}**. Radcal keeps nothing here after that date — this is temporary transfer, not storage.
</x-mail::panel>

**Uploading files does not open a support request.** Please contact your Radcal representative directly and let them know your reference number so they can find your files.

Thanks,<br>
Radcal File Exchange
</x-mail::message>
