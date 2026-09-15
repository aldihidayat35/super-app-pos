<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessNotificationSetting extends Model
{
    protected $fillable = ['event_key', 'name', 'description', 'recipient_roles', 'location_scoped', 'cooldown_minutes', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['recipient_roles' => 'array', 'location_scoped' => 'boolean', 'cooldown_minutes' => 'integer', 'is_active' => 'boolean'];
    }
}
