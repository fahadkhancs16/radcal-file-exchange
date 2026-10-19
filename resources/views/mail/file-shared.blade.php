<x-mail::message>
# {{ $exchange->customer_name }} shared {{ $files->count() === 1 ? 'a file' : $files->count().' files' }} with you

@if (filled($exchange->company))
**From:** {{ $exchange->customer_name }}, {{ $exchange->company }} ({{ $exchange->email }})
@else
**From:** {{ $exchange->customer_name }} ({{ $exchange->email }})
@endif

@if (filled($explanation))
<x-mail::panel>
{{ $explanation }}
</x-mail::panel>

@endif
@foreach ($files as $file)
<x-mail::button :url="route('share.show', $file->share_token)">
View / download {{ $file->original_filename }} ({{ $file->humanSize() }})
</x-mail::button>

@endforeach
<x-mail::panel>
These links stop working on **{{ $exchange->expires_at->format('F j, Y') }}**, when the files are permanently deleted. Anyone with a link can open its file, so don't forward this email to people who shouldn't have it.
</x-mail::panel>

Radcal File Exchange sent this message on behalf of {{ $exchange->customer_name }}. If you weren't expecting it, you can ignore it.
</x-mail::message>
