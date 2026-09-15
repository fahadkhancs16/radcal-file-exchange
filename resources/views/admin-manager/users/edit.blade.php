<x-layouts.manager title="Edit staff account">
    <div class="card">
        <div class="card-head"><h2>Edit {{ $editing->email }}</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin-manager.users.update', $editing) }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $editing->name) }}" required autofocus>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $editing->email) }}" required>
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="password">New password <span class="hint">(leave blank to keep the current one)</span></label>
                    <input type="password" id="password" name="password">
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $editing->is_admin)) style="width:auto;margin-right:.4rem">
                        Can sign in to /admin
                    </label>
                    @error('is_admin') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Save changes</button>
                    <a href="{{ route('admin-manager.users.index') }}" class="btn link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.manager>
