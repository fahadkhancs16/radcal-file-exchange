<x-layouts.manager title="New staff account">
    <div class="card">
        <div class="card-head"><h2>New staff account</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin-manager.users.store') }}">
                @csrf
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="password">Password <span class="hint">(leave blank to generate one)</span></label>
                    <input type="password" id="password" name="password">
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_admin" value="1" checked style="width:auto;margin-right:.4rem">
                        Can sign in to /admin
                    </label>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Create account</button>
                    <a href="{{ route('admin-manager.users.index') }}" class="btn link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.manager>
