<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Raw stored value, decrypted on the fly when is_encrypted=true.
     */
    public function decryptedValue(): ?string
    {
        if ($this->value === null || $this->value === '') {
            return $this->value;
        }
        if (! $this->is_encrypted) {
            return $this->value;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setSecretValue(?string $plain): void
    {
        $this->value = $plain === null || $plain === '' ? null : Crypt::encryptString($plain);
        $this->is_encrypted = true;
    }
}
