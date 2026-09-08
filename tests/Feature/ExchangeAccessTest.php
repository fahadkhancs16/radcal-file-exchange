<?php

use App\Models\Exchange;

it('opens the workspace with the right code and password', function () {
    $exchange = Exchange::factory()->password('let-me-in')->create();

    $this->post('/access', ['code' => $exchange->code, 'password' => 'let-me-in'])
        ->assertRedirect(route('exchange.workspace', $exchange));
});

it('fails a wrong password without revealing the code exists', function () {
    $exchange = Exchange::factory()->password('let-me-in')->create();

    $this->post('/access', ['code' => $exchange->code, 'password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->get(route('exchange.workspace', $exchange))->assertNotFound();
});

it('fails an unknown code the same way as a wrong password', function () {
    $this->post('/access', ['code' => 'NOPE9999', 'password' => 'whatever'])
        ->assertSessionHasErrors('password');
});

it('refuses an expired exchange', function () {
    $exchange = Exchange::factory()->expired()->password('let-me-in')->create();

    $this->post('/access', ['code' => $exchange->code, 'password' => 'let-me-in'])
        ->assertSessionHasErrors('password');
});

it('refuses a disabled exchange', function () {
    $exchange = Exchange::factory()->disabled()->password('let-me-in')->create();

    $this->post('/access', ['code' => $exchange->code, 'password' => 'let-me-in'])
        ->assertSessionHasErrors('password');
});

it('shows a password prompt on the direct link and lets a valid password in', function () {
    $exchange = Exchange::factory()->password('direct-open')->create();

    $this->get(route('exchange.landing', $exchange))
        ->assertOk()
        ->assertSee('Password');

    $this->post(route('exchange.access', $exchange->code), ['password' => 'direct-open'])
        ->assertRedirect(route('exchange.workspace', $exchange));
});

it('locks out after five bad password attempts', function () {
    $exchange = Exchange::factory()->password('let-me-in')->create();

    foreach (range(1, 5) as $i) {
        $this->post('/access', ['code' => $exchange->code, 'password' => 'wrong']);
    }

    $this->post('/access', ['code' => $exchange->code, 'password' => 'let-me-in'])
        ->assertSessionHasErrors('password');
});
