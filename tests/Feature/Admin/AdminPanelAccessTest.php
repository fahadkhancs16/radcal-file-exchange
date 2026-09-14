<?php

use App\Models\User;

it('sends a guest to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an admin sign in and reach the dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('blocks a non-admin user from the panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('carries the security headers onto admin pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('X-Frame-Options', 'DENY');
});
