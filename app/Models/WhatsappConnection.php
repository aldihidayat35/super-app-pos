<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConnection extends Model
{
    protected $fillable = ['session_id', 'status', 'phone_number', 'account_name', 'connected_at', 'last_checked_at', 'last_error', 'last_action_by'];

    protected function casts(): array
    {
        return ['connected_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_action_by');
    }
}
