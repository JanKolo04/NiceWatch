<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Host extends Model
{
    use HasFactory;

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'name',
        'hostname',
        'api_token',
        'status',
        'last_seen_at',
    ];

    // api_token is visible to authenticated panel users on purpose:
    // they need to copy it into the agent installer one-liner. Anyone with
    // access to the panel can already enumerate hosts and rotate tokens, so
    // hiding it would only add friction (no security benefit). Don't expose
    // it through public JSON responses — keep usage to authenticated views.

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class)->latest('id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class)->latest('id');
    }

    public function latestSnapshot(): ?Snapshot
    {
        return $this->snapshots()->first();
    }

    protected function isOnline(): Attribute
    {
        return Attribute::get(function (): bool {
            if ($this->last_seen_at === null) {
                return false;
            }

            $threshold = (int) app(SettingsRepository::class)->alertConfig()['offline_threshold_seconds'];

            return $this->last_seen_at->gte(Carbon::now()->subSeconds($threshold));
        });
    }
}
