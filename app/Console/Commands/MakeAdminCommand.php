<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Create (or promote) a Radcal staff account that can sign in to /admin.
 * Deployment convenience — see CLAUDE.md / the build plan's Plesk runbook.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'app:make-admin {email? : Email address} {name? : Full name}';

    protected $description = 'Create or promote a Radcal Administration user';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?? $this->ask('Email address'));
        $name = (string) ($this->argument('name') ?? $this->ask('Full name'));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            ['email' => ['required', 'email:rfc'], 'name' => ['required', 'string', 'max:120']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $existing->forceFill(['is_admin' => true])->save();
            $this->info("{$email} already existed — promoted to admin.");

            return self::SUCCESS;
        }

        $password = $this->secret('Password (leave blank to generate one)') ?: null;
        $generated = $password === null;
        $password ??= PasswordGenerator::generate(16);

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info("Admin user {$email} created.");

        if ($generated) {
            $this->warn("Generated password: {$password}");
            $this->line('Save this now — it is not shown again.');
        }

        return self::SUCCESS;
    }
}
