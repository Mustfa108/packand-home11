<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function getPlainValueAttribute(): ?string
    {
        if ($this->value === null || $this->value === '') {
            return null;
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

    public static function put(string $key, ?string $value, bool $encrypt = false): self
    {
        $stored = $value;
        if ($encrypt && $value !== null && $value !== '') {
            $stored = Crypt::encryptString($value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'is_encrypted' => $encrypt,
            ]
        );
    }
}
