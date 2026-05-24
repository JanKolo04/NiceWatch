<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    public const TYPE_DISK_HIGH = 'disk_high';
    public const TYPE_HOST_OFFLINE = 'host_offline';

    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'host_id',
        'type',
        'key',
        'severity',
        'message',
        'payload',
        'triggered_at',
        'notified_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'triggered_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
