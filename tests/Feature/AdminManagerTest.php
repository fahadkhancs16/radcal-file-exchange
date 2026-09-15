<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['admin_manager.password' => 'correct-horse-battery-staple']);
});

it('shows the gate to a guest', function () {
    $this->get(route('admin-manager.gate'))->assertOk()->assertSee('Password');
});

it('blocks the users page without unlocking first', function () {
    $this->get(route('admin-manager.users.index'))->assertRedirect(route('admin-manager.gate'));
});

it('rejects a wrong password', function () {
    $this->post(route('admin-manager.authenticate'), ['password' => 'nope'])
        ->assertSessionHasErrors('password');

    expect(session('admin_manager.authenticated'))->toBeNull();
});

it('fails closed when no password is configured', function () {
    config(['admin_manager.password' => '']);

    $this->post(route('admin-manager.authenticate'), ['password' => ''])
        ->assertSessionHasErrors('password');
});

it('unlocks the users page with the correct password', function () {
    $this->post(route('admin-manager.authenticate'), ['password' => 'correct-horse-battery-staple'])
        ->assertRedirect(route('admin-manager.users.index'));

    $this->get(route('admin-manager.users.index'))->assertOk();
});

it('locks out after too many wrong attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('admin-manager.authenticate'), ['password' => 'nope']);
    }

    $this->post(route('admin-manager.authenticate'), ['password' => 'correct-horse-battery-staple'])
        ->assertSessionHasErrors('password');
});

it('logout re-locks the page', function () {
    $this->withSession(['admin_manager.authenticated' => true]);

    $this->post(route('admin-manager.logout'));

    $this->get(route('admin-manager.users.index'))->assertRedirect(route('admin-manager.gate'));
});

describe('once unlocked', function () {
    beforeEach(function () {
        $this->withSession(['admin_manager.authenticated' => true]);
    });

    it('lists staff accounts', function () {
        $admin = User::factory()->create(['email' => 'existing@example.com']);

        $this->get(route('admin-manager.users.index'))->assertOk()->assertSee('existing@example.com');
    });

    it('creates a user with a generated password when none is given', function () {
        $this->post(route('admin-manager.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => '',
            'is_admin' => '1',
        ])->assertRedirect(route('admin-manager.users.index'));

        $user = User::where('email', 'new@example.com')->sole();
        expect($user->is_admin)->toBeTrue();
        $this->get(route('admin-manager.users.index'))->assertSee('Generated password:', false);
    });

    it('creates a user with a chosen password', function () {
        $this->post(route('admin-manager.users.store'), [
            'name' => 'New Person',
            'email' => 'new2@example.com',
            'password' => 'a-chosen-password',
            'is_admin' => '1',
        ]);

        expect(Hash::check('a-chosen-password', User::where('email', 'new2@example.com')->sole()->password))->toBeTrue();
    });

    it('updates a user without changing the password when left blank', function () {
        $user = User::factory()->create(['password' => Hash::make('original')]);

        $this->put(route('admin-manager.users.update', $user), [
            'name' => 'Renamed',
            'email' => $user->email,
            'password' => '',
            'is_admin' => '1',
        ]);

        $fresh = $user->fresh();
        expect($fresh->name)->toBe('Renamed')
            ->and(Hash::check('original', $fresh->password))->toBeTrue();
    });

    it('deletes a user', function () {
        User::factory()->create(['is_admin' => true]); // keep at least one other admin
        $user = User::factory()->create(['is_admin' => false]);

        $this->delete(route('admin-manager.users.destroy', $user))
            ->assertRedirect(route('admin-manager.users.index'));

        expect(User::find($user->id))->toBeNull();
    });

    it('refuses to delete the only remaining admin', function () {
        User::query()->delete();
        $onlyAdmin = User::factory()->create(['is_admin' => true]);

        $this->delete(route('admin-manager.users.destroy', $onlyAdmin))
            ->assertRedirect(route('admin-manager.users.index'))
            ->assertSessionHas('error');

        expect(User::find($onlyAdmin->id))->not->toBeNull();
    });

    it('refuses to demote the only remaining admin', function () {
        User::query()->delete();
        $onlyAdmin = User::factory()->create(['is_admin' => true]);

        $this->put(route('admin-manager.users.update', $onlyAdmin), [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'password' => '',
            // is_admin omitted -> false
        ])->assertSessionHasErrors('is_admin');

        expect($onlyAdmin->fresh()->is_admin)->toBeTrue();
    });
});
