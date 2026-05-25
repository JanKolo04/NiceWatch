<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('nicewatch:user:create {email} {--name= : Display name (defaults to local part of the email)} {--password= : Plaintext password; if omitted a random one is generated and printed}')]
#[Description('Create a panel user. Use when public registration is closed (default).')]
class UserCreate extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email.");

            return self::INVALID;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("User '{$email}' already exists.");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: explode('@', $email)[0]);
        $password = (string) $this->option('password');
        $generated = false;
        if ($password === '') {
            $password = Str::random(20);
            $generated = true;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Created user {$email} (name: {$name}).");
        if ($generated) {
            $this->newLine();
            $this->line('Generated password (copy now — not stored anywhere else):');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
