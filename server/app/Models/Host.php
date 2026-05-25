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
        'api_token_hash',
        'status',
        'last_seen_at',
    ];

    // Only the SHA-256 hash of the bearer token is persisted; the plaintext
    // value is shown to the operator exactly once (via session flash after
    // create/rotate) and never again. Token comparison happens against the hash.

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Generate a fresh bearer token and return the plaintext.
     * Caller is responsible for surfacing it to the operator once.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
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
