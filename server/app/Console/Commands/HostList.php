<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nicewatch:host:list')]
#[Description('List registered hosts and their current status.')]
class HostList extends Command
{
    public function handle(): int
    {
        $hosts = Host::query()->orderBy('id')->get();

        if ($hosts->isEmpty()) {
            $this->line('No hosts registered. Use nicewatch:host:create to add one.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Hostname', 'Status', 'Last seen'],
            $hosts->map(fn (Host $h): array => [
                $h->id,
                $h->name,
                $h->hostname ?? '—',
                $h->status,
                $h->last_seen_at?->diffForHumans() ?? 'never',
            ])->all()
        );

        return self::SUCCESS;
    }
}
