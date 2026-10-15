<?php

use App\Enums\FileOwner;
use App\Livewire\ExchangeWorkspace;
use App\Mail\ColleagueFilesSharedMail;
use App\Mail\ColleagueInvitedMail;
use App\Models\Exchange;
use App\Services\ColleagueService;
use App\Services\FileService;
use App\Support\ExchangeSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('exchanges');
    Mail::fake();
    $this->exchange = Exchange::factory()->password('owner-pass')->create([
        'email' => 'owner@acme.test',
        'max_file_size' => 1024 * 1024,
    ]);
    app(ExchangeSession::class)->grant($this->exchange);
});

it('sends files to an existing colleague, who sees them as received', function () {
    app(ColleagueService::class)->invite($this->exchange, 'pat@acme.test', 'Pat', 'owner@acme.test');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', $this->exchange->id.':pat@acme.test')
        ->set('teamUploads', [UploadedFile::fake()->create('plan.pdf', 10)])
        ->set('teamExplanation', 'Please review.')
        ->call('sendToColleague')
        ->assertHasNoErrors();

    $file = $this->exchange->colleagueFiles()->first();
    expect($file->uploaded_by_email)->toBe('owner@acme.test')
        ->and($file->recipient_email)->toBe('pat@acme.test');

    Mail::assertSent(ColleagueFilesSharedMail::class, fn ($m) => $m->hasTo('pat@acme.test')
        && $m->sender === 'owner@acme.test' && $m->explanation === 'Please review.');
    // It is not a transfer to Radcal.
    expect($this->exchange->customerFiles()->count())->toBe(0);

    app(ExchangeSession::class)->grant($this->exchange, 'pat@acme.test');
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadColleagueFile', $file->id)
        ->assertFileDownloaded('plan.pdf');
});

it('invites a new colleague and sends them the file in one step', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', '__new__')
        ->set('newEmail', 'New.Person@Acme.test')
        ->set('newName', 'New Person')
        ->set('teamUploads', [UploadedFile::fake()->create('spec.pdf', 10)])
        ->set('teamExplanation', 'Welcome aboard.')
        ->call('sendToColleague')
        ->assertHasNoErrors();

    $member = $this->exchange->members()->first();
    expect($member->email)->toBe('new.person@acme.test');

    Mail::assertSent(ColleagueInvitedMail::class, fn ($m) => $m->hasTo('new.person@acme.test')
        && $member->checkPassword($m->password));
    Mail::assertSent(ColleagueFilesSharedMail::class, fn ($m) => $m->hasTo('new.person@acme.test'));
    expect($this->exchange->colleagueFiles()->count())->toBe(1);
});

it('requires a file, an explanation and a recipient', function () {
    app(ColleagueService::class)->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('sendToColleague')
        ->assertHasErrors(['recipient', 'teamUploads', 'teamExplanation']);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', '__new__')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasErrors('newEmail');

    expect($this->exchange->colleagueFiles()->count())->toBe(0);
});

it('does not let anyone send files to themselves or to a stranger', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', $this->exchange->id.':owner@acme.test')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasErrors('recipient');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', $this->exchange->id.':stranger@elsewhere.test')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasErrors('recipient');

    expect($this->exchange->colleagueFiles()->count())->toBe(0);
});

it('keeps a transfer private to its sender and recipient', function () {
    $service = app(ColleagueService::class);
    $service->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');
    $service->invite($this->exchange, 'sam@acme.test', null, 'owner@acme.test');

    $file = app(FileService::class)->store(
        $this->exchange, FileOwner::Colleague, UploadedFile::fake()->create('private.pdf', 10),
        uploadedByEmail: 'owner@acme.test', recipientEmail: 'pat@acme.test', recipientExchangeId: $this->exchange->id,
    );

    app(ExchangeSession::class)->grant($this->exchange, 'sam@acme.test');
    expect(fn () => Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadColleagueFile', $file->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(fn () => $this->exchange->files()->involving($this->exchange->id, 'sam@acme.test')->findOrFail($file->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('lets the sender delete only the files they sent', function () {
    app(ColleagueService::class)->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');
    $files = app(FileService::class);

    $sent = $files->store($this->exchange, FileOwner::Colleague, UploadedFile::fake()->create('mine.pdf', 10),
        uploadedByEmail: 'owner@acme.test', recipientEmail: 'pat@acme.test', recipientExchangeId: $this->exchange->id);
    $received = $files->store($this->exchange, FileOwner::Colleague, UploadedFile::fake()->create('theirs.pdf', 10),
        uploadedByEmail: 'pat@acme.test', recipientEmail: 'owner@acme.test', recipientExchangeId: $this->exchange->id);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('selected', [$sent->id, $received->id])
        ->call('deleteSentColleagueFiles');

    expect($this->exchange->colleagueFiles()->pluck('original_filename')->all())->toBe(['theirs.pdf']);
});

it('replaces a file only for the same sender and recipient pair', function () {
    $service = app(ColleagueService::class);
    $service->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');
    $service->invite($this->exchange, 'sam@acme.test', null, 'owner@acme.test');
    $files = app(FileService::class);

    foreach (['pat@acme.test', 'pat@acme.test', 'sam@acme.test'] as $to) {
        $files->store($this->exchange, FileOwner::Colleague, UploadedFile::fake()->create('same.pdf', 10),
            uploadedByEmail: 'owner@acme.test', recipientEmail: $to, recipientExchangeId: $this->exchange->id);
    }

    expect($this->exchange->colleagueFiles()->count())->toBe(2);
});

it('lets a colleague in with their own password, and not with a wrong one', function () {
    ['password' => $password] = app(ColleagueService::class)
        ->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');

    $this->post('/access', ['code' => $this->exchange->code, 'password' => 'nope'])
        ->assertSessionHasErrors('password');

    $this->post('/access', ['code' => $this->exchange->code, 'password' => $password])
        ->assertRedirect(route('exchange.workspace', $this->exchange));

    expect(app(ExchangeSession::class)->customerEmail())->toBe('pat@acme.test');
});

it('enforces the colleague limit', function () {
    config(['exchange.max_colleagues' => 1]);
    app(ColleagueService::class)->invite($this->exchange, 'a@acme.test', null, 'owner@acme.test');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', '__new__')
        ->set('newEmail', 'b@acme.test')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasErrors('newEmail');
});

it('lets a customer download a customer-side file', function () {
    $file = app(FileService::class)->store(
        $this->exchange, FileOwner::Customer, UploadedFile::fake()->create('shared.pdf', 10)
    );

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadCustomerFile', $file->id)
        ->assertFileDownloaded('shared.pdf');
});

it('lets only the exchange contact remove a colleague', function () {
    $service = app(ColleagueService::class);
    ['member' => $pat] = $service->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');
    $service->invite($this->exchange, 'sam@acme.test', null, 'owner@acme.test');

    app(ExchangeSession::class)->grant($this->exchange, 'sam@acme.test');
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('removeColleague', $pat->id);
    expect($this->exchange->members()->count())->toBe(2);

    app(ExchangeSession::class)->grant($this->exchange, 'owner@acme.test');
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('removeColleague', $pat->id);
    expect($this->exchange->members()->pluck('email')->all())->toBe(['sam@acme.test']);
});

it('lists every other active customer in the team, but not the signed-in one', function () {
    $other = Exchange::factory()->create(['email' => 'other@beta.test', 'customer_name' => 'Beta Contact', 'company' => 'Beta']);
    Exchange::factory()->expired()->create(['email' => 'gone@beta.test']);
    Exchange::factory()->disabled()->create(['email' => 'off@beta.test']);
    app(ColleagueService::class)->invite($this->exchange, 'pat@acme.test', null, 'owner@acme.test');

    $team = app(ColleagueService::class)->team($this->exchange);

    expect($team->pluck('email')->all())->toContain('owner@acme.test', 'pat@acme.test', 'other@beta.test')
        ->not->toContain('gone@beta.test', 'off@beta.test');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('showSection', 'team')
        ->assertSee('Beta Contact')
        ->assertSee('other@beta.test')
        ->assertSee('pat@acme.test')
        ->assertDontSee('owner@acme.test');
});

it('sends files to a customer in another exchange, who downloads them from their own', function () {
    $other = Exchange::factory()->create(['email' => 'other@beta.test']);
    config(['exchange.notifications.staff_email' => 'ops@radcal.com']);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', $other->id.':other@beta.test')
        ->set('teamUploads', [UploadedFile::fake()->create('across.pdf', 10)])
        ->set('teamExplanation', 'Cross-company hand-off.')
        ->call('sendToColleague')
        ->assertHasNoErrors();

    $file = $this->exchange->colleagueFiles()->first();
    expect($file->recipient_exchange_id)->toBe($other->id)
        ->and($other->files()->count())->toBe(0);

    Mail::assertSent(ColleagueFilesSharedMail::class, fn ($m) => $m->hasTo('other@beta.test')
        && $m->reference() === $other->code);

    // The recipient signs in to THEIR exchange and sees and downloads it.
    app(ExchangeSession::class)->grant($other);
    Livewire::test(ExchangeWorkspace::class, ['code' => $other->code])
        ->call('showSection', 'colleague_files')
        ->assertSee('across.pdf')
        ->call('downloadColleagueFile', $file->id)
        ->assertFileDownloaded('across.pdf');

    // A third customer cannot.
    $third = Exchange::factory()->create();
    app(ExchangeSession::class)->grant($third);
    expect(fn () => Livewire::test(ExchangeWorkspace::class, ['code' => $third->code])
        ->call('downloadColleagueFile', $file->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('refuses to guess when a typed address belongs to several exchanges', function () {
    $a = Exchange::factory()->create(['email' => 'shared@beta.test']);
    $b = Exchange::factory()->create(['email' => 'shared@beta.test']);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', '__new__')
        ->set('newEmail', 'shared@beta.test')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasErrors('newEmail')
        ->assertSee($a->code);

    expect($this->exchange->colleagueFiles()->count())->toBe(0);

    // Picking the specific exchange from the Team list works.
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('chooseRecipient', $b->id.':shared@beta.test')
        ->set('teamUploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('teamExplanation', 'x')
        ->call('sendToColleague')
        ->assertHasNoErrors();

    expect($this->exchange->colleagueFiles()->first()->recipient_exchange_id)->toBe($b->id);
});

it('shows each exchange reference in the team list', function () {
    $a = Exchange::factory()->create(['email' => 'shared@beta.test']);
    $b = Exchange::factory()->create(['email' => 'shared@beta.test']);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('showSection', 'team')
        ->assertSee($a->code)
        ->assertSee($b->code);
});
