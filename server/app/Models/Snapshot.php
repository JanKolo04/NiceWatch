<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'payload',
        'collected_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'collected_at' => 'datetime',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
