<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An anonymous browser push subscription from the student page. Holds no
 * personal identifiers - see the migration.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'endpoint',
        'endpoint_hash',
        'p256dh_key',
        'auth_token',
        'last_pushed_at',
    ];

    protected $casts = [
        'last_pushed_at' => 'datetime',
    ];

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
