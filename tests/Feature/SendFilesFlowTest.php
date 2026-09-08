<?php

use App\Mail\ExchangeReadyMail;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerification;
use App\Models\Exchange;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

function startSend(array $overrides = []): array
{
    return array_merge([
        'customer_name' => 'Marco Feld',
        'company' => 'Feld Medical Physics',
        'email' => 'marco@example.com',
        'description' => 'Dose-rate discrepancy traces.',
    ], $overrides);
}

it('emails a verification code and stores a hashed pending verification', function () {
    $this->post('/send', startSend())->assertRedirect(route('send.verify'));

    Mail::assertSent(VerificationCodeMail::class);
    $row = EmailVerification::sole();
    expect($row->email)->toBe('marco@example.com')
        ->and($row->code_hash)->not->toBe('')
        ->and($row->payload['customer_name'])->toBe('Marco Feld');
});

it('rejects an invalid customer form', function () {
    $this->post('/send', startSend(['email' => 'not-an-email', 'customer_name' => '']))
        ->assertSessionHasErrors(['email', 'customer_name']);
});

it('creates an exchange, mails the link, and opens the workspace on a correct code', function () {
    $this->post('/send', startSend());

    // Reach into the mailable to learn the code a real customer would receive.
    $code = null;
    Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $response = $this->post('/send/verify', ['code' => $code]);

    $exchange = Exchange::sole();
    $response->assertRedirect(route('exchange.workspace', $exchange));
    expect($exchange->origin->value)->toBe('customer_initiated')
        ->and($exchange->customer_name)->toBe('Marco Feld');
    Mail::assertSent(ExchangeReadyMail::class);
});

it('rejects a wrong code and does not create an exchange', function () {
    $this->post('/send', startSend());

    $this->post('/send/verify', ['code' => '000000'])->assertSessionHas('error');

    expect(Exchange::count())->toBe(0);
});

it('locks the verification after five wrong attempts', function () {
    $this->post('/send', startSend());

    foreach (range(1, 5) as $i) {
        $this->post('/send/verify', ['code' => '999999']);
    }

    expect(EmailVerification::sole()->isLocked())->toBeTrue();
});

it('sends the customer straight to the start page when there is no pending verification', function () {
    $this->get('/send/verify')->assertRedirect(route('send.start'));
});
