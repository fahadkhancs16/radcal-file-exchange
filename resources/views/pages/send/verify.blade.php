<x-layouts.app title="Verify your email">
    <h1>Enter your code</h1>
    <p class="lede">We emailed a 6-digit code to <strong>{{ $email }}</strong>. It is valid for {{ config('exchange.verification.ttl_minutes') }} minutes.</p>

    <div class="card">
        <form method="POST" action="{{ route('send.verify.submit') }}">
            @csrf
            <div class="field">
                <label for="code">Verification code</label>
                <input type="text" id="code" name="code" class="code-input" inputmode="numeric"
                       autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                @error('code') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn">Verify and continue</button>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('send.verify.resend') }}" style="margin-top:1rem">
        @csrf
        <button type="submit" class="btn link">Didn't get it? Send a new code</button>
    </form>
</x-layouts.app>
