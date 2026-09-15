<x-layouts.manager title="Staff accounts">
    <div class="card">
        <div class="card-head">
            <h2>Staff accounts</h2>
            <span class="count">{{ $users->count() }}</span>
            <span class="spacer"></span>
            <a href="{{ route('admin-manager.users.create') }}" class="btn sm">New account</a>
        </div>
        <div class="table-wrap">
            <table class="files">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="right">Created</th>
                        <th class="right"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="status-pill {{ $user->is_admin ? 'active' : 'warn' }}">
                                {{ $user->is_admin ? 'Admin' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="right size">{{ $user->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="right">
                            <a href="{{ route('admin-manager.users.edit', $user) }}" class="btn secondary sm">Edit</a>
                            <form method="POST" action="{{ route('admin-manager.users.destroy', $user) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete {{ $user->email }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="5">No staff accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.manager>
