<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates the bootstrap admin account on first deployment.
     *
     * Operator MUST provide credentials via env vars before the first
     * `docker compose up` — there is no hard-coded fallback. If NICEWATCH_ADMIN_PASSWORD
     * is empty (or set to the literal placeholder 'CHANGE_ME'), the seeder
     * generates a random one-time password and prints it to the container log.
     *
     * Set NICEWATCH_ADMIN_EMAIL to an empty string to skip auto-seeding entirely
     * (use `php artisan nicewatch:user:create` instead).
     *
     * Idempotent: re-runs leave an existing user alone.
     *
     *   NICEWATCH_ADMIN_EMAIL    (required to seed)
     *   NICEWATCH_ADMIN_PASSWORD (required — must NOT be 'CHANGE_ME' or empty in prod)
     *   NICEWATCH_ADMIN_NAME     (optional, defaults to 'Admin')
     */
    public function run(): void
    {
        $email = (string) env('NICEWATCH_ADMIN_EMAIL', '');
        if ($email === '') {
            $this->command?->info('AdminUserSeeder: NICEWATCH_ADMIN_EMAIL is empty, skipping.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("AdminUserSeeder: user {$email} already exists, leaving untouched.");

            return;
        }

        $configured = (string) env('NICEWATCH_ADMIN_PASSWORD', '');
        $generated = false;

        if ($configured === '' || strcasecmp($configured, 'CHANGE_ME') === 0) {
            $configured = Str::random(20);
            $generated = true;
        }

        User::create([
            'name' => (string) env('NICEWATCH_ADMIN_NAME', 'Admin'),
            'email' => $email,
            'password' => Hash::make($configured),
            'email_verified_at' => now(),
        ]);

        if ($generated) {
            $this->command?->newLine();
            $this->command?->warn('=====================================================');
            $this->command?->warn(' AdminUserSeeder: created admin with a RANDOM password');
            $this->command?->warn('=====================================================');
            $this->command?->line(" email:    {$email}");
            $this->command?->line(" password: {$configured}");
            $this->command?->newLine();
            $this->command?->warn(' Copy it NOW — it will not be shown again. Then either:');
            $this->command?->warn(' (a) log in and change the password in /profile, or');
            $this->command?->warn(' (b) set NICEWATCH_ADMIN_PASSWORD in .env to your value.');
            $this->command?->warn('=====================================================');
            $this->command?->newLine();
        } else {
            $this->command?->info("AdminUserSeeder: created admin account {$email}.");
        }
    }
}
