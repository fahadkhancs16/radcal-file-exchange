<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A back-door for managing Radcal staff accounts, gated by a single shared
 * password from config/admin_manager.php instead of a user login — the
 * recovery path if every admin password is lost. Lives at a private,
 * unlinked URL (see ADMIN_MANAGER_PATH). Every create/update/delete is
 * logged, since it bypasses the normal admin-attributed activity trail.
 */
class AdminManagerController extends Controller
{
    public function showGate(): View
    {
        return view('admin-manager.gate');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $configured = (string) config('admin_manager.password');
        $key = 'admin-manager-auth:'.$request->ip();
        $maxAttempts = (int) config('admin_manager.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $data = $request->validate(['password' => ['required', 'string']]);

        if ($configured === '' || ! hash_equals($configured, $data['password'])) {
            RateLimiter::hit($key, (int) config('admin_manager.decay_minutes') * 60);
            Log::warning('admin-manager: failed unlock attempt', ['ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('admin_manager.authenticated', true);

        Log::info('admin-manager: unlocked', ['ip' => $request->ip()]);

        return redirect()->route('admin-manager.users.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_manager.authenticated');

        return redirect()->route('admin-manager.gate');
    }

    public function index(): View
    {
        return view('admin-manager.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin-manager.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:10'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $generated = blank($data['password'] ?? null);
        $password = $generated ? PasswordGenerator::generate(16) : $data['password'];

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'is_admin' => $request->boolean('is_admin', true),
        ]);

        Log::info('admin-manager: user created', ['ip' => $request->ip(), 'user_id' => $user->id, 'email' => $user->email]);

        $status = "Created {$user->email}.";
        if ($generated) {
            $status .= " Generated password: {$password} — save this now, it isn't shown again.";
        }

        return redirect()->route('admin-manager.users.index')->with('status', $status);
    }

    public function edit(User $user): View
    {
        return view('admin-manager.users.edit', ['editing' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:10'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $wantsAdmin = $request->boolean('is_admin', false);

        if ($user->is_admin && ! $wantsAdmin && User::where('is_admin', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'is_admin' => 'This is the only remaining admin — promote someone else before removing this one.',
            ]);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => $wantsAdmin,
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        Log::info('admin-manager: user updated', ['ip' => $request->ip(), 'user_id' => $user->id]);

        return redirect()->route('admin-manager.users.index')->with('status', "Updated {$user->email}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return redirect()->route('admin-manager.users.index')
                ->with('error', 'Cannot delete the only remaining admin.');
        }

        $email = $user->email;
        $user->delete();

        Log::info('admin-manager: user deleted', ['ip' => $request->ip(), 'email' => $email]);

        return redirect()->route('admin-manager.users.index')->with('status', "Deleted {$email}.");
    }
}
