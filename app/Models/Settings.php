<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'call_to_action',
    ];

    public static function instance(): static
    {
        /** @var static */
        return static::firstOrCreate([]);
    }
}
