<x-layouts.app title="Staff access">
    <h1>Staff access</h1>
    <p class="sub">Enter the shared access password to manage Radcal staff accounts.</p>

    <div class="panel">
        <form method="POST" action="{{ route('admin-manager.authenticate') }}">
            @csrf
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autofocus>
                @error('password') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn block">Continue</button>
            </div>
        </form>
    </div>
</x-layouts.app>
