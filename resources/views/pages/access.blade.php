<x-layouts.app title="Access a file exchange">
    <h1>Access a file exchange</h1>
    <p class="sub">Enter the exchange code and password Radcal gave you.</p>

    <div class="panel">
        <form method="POST" action="{{ route('access.enter') }}">
            @csrf
            <div class="field">
                <label for="code">Exchange code</label>
                <input type="text" id="code" name="code" value="{{ old('code') }}" required autofocus
                       autocapitalize="characters" spellcheck="false">
                @error('code') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                @error('password') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn">Open exchange</button>
                <a href="{{ route('home') }}" class="btn link">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.app>
