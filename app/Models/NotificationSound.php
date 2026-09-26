<?php

namespace App\Models;

use Database\Factories\NotificationSoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'path', 'is_active'])]
class NotificationSound extends Model
{
    /** @use HasFactory<NotificationSoundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * URL of the active uploaded sound, or null when the built-in melody is in use.
     */
    public static function activeUrl(): ?string
    {
        $sound = static::query()->where('is_active', true)->first();

        return $sound ? route('sounds.file', $sound) : null;
    }
}
