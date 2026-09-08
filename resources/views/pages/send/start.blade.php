<x-layouts.app title="Send files to Radcal">
    <h1>Send files to Radcal</h1>
    <p class="lede">Tell us who you are and what you are sending. We will email a 6-digit code to confirm your address before you upload.</p>

    <div class="card">
        <form method="POST" action="{{ route('send.store') }}">
            @csrf

            <div class="field">
                <label for="customer_name">Your name</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required autofocus>
                @error('customer_name') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="company">Company <span class="hint">(optional)</span></label>
                <input type="text" id="company" name="company" value="{{ old('company') }}">
                @error('company') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                @error('email') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="description">What are you sending? <span class="hint">(optional)</span></label>
                <textarea id="description" name="description">{{ old('description') }}</textarea>
                @error('description') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="actions">
                <button type="submit" class="btn">Continue</button>
                <a href="{{ route('home') }}" class="btn link">Cancel</a>
            </div>
        </form>
    </div>

    <p class="lede" style="margin-top:1.5rem;font-size:.88rem">
        Sending files here does not open a support request. After uploading, contact your Radcal representative directly.
    </p>
</x-layouts.app>
