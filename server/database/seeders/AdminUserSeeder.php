<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates an initial admin account so a fresh deployment is immediately usable.
     *
     * Reads credentials from env vars so the same image works for dev demos and
     * production installs:
     *   NICEWATCH_ADMIN_EMAIL    (default: admin@nicewatch.local)
     *   NICEWATCH_ADMIN_PASSWORD (default: admin — change in production!)
     *   NICEWATCH_ADMIN_NAME     (default: Admin)
     *
     * Idempotent: re-runs do nothing if a user with that email already exists.
     * Set NICEWATCH_ADMIN_EMAIL to an empty string to disable auto-seeding.
     */
    public function run(): void
    {
        $email = env('NICEWATCH_ADMIN_EMAIL', 'admin@nicewatch.local');
        if (! is_string($email) || $email === '') {
            $this->command?->info('AdminUserSeeder: NICEWATCH_ADMIN_EMAIL is empty, skipping.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("AdminUserSeeder: user {$email} already exists, leaving untouched.");

            return;
        }

        User::create([
            'name' => env('NICEWATCH_ADMIN_NAME', 'Admin'),
            'email' => $email,
            'password' => Hash::make((string) env('NICEWATCH_ADMIN_PASSWORD', 'admin')),
            'email_verified_at' => now(),
        ]);

        $this->command?->info("AdminUserSeeder: created admin account {$email}.");
    }
}
