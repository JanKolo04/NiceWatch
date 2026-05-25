<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nicewatch:host:create {name : Human-friendly host name (e.g. web01)}')]
#[Description('Create a new host and print its bearer token (copy into agent config).')]
class HostCreate extends Command
{
    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (Host::query()->where('name', $name)->exists()) {
            $this->error("Host '{$name}' already exists.");

            return self::FAILURE;
        }

        $token = Host::generateToken();
        $host = Host::create([
            'name' => $name,
            'api_token_hash' => Host::hashToken($token),
            'status' => Host::STATUS_UNKNOWN,
        ]);

        $this->info("Created host #{$host->id} '{$host->name}'");
        $this->newLine();
        $this->line('Bearer token (copy NOW — only stored as SHA-256 hash, no way to recover):');
        $this->line($token);

        return self::SUCCESS;
    }
}
