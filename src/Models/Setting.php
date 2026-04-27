<?php

declare(strict_types=1);

namespace Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = [
        'user_id',
        'group',
        'name',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
