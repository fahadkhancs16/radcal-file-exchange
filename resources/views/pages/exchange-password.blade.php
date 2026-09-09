<x-layouts.app title="Enter exchange password">
    <h1>Exchange {{ $code }}</h1>
    <p class="sub">Enter the password Radcal gave you for this exchange.</p>

    <div class="panel">
        <form method="POST" action="{{ route('exchange.access', $code) }}">
            @csrf
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autofocus>
                @error('password') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn block">Open exchange</button>
            </div>
        </form>
    </div>
</x-layouts.app>
