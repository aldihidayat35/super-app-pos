<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string|null $target_override
 * @property string|null $maximum_bonus_override
 * @property-read User $user
 */
class StaffBonusProgramUser extends Model
{
    protected $fillable = ['staff_bonus_program_id', 'user_id', 'target_override', 'maximum_bonus_override'];

    protected function casts(): array
    {
        return ['target_override' => 'decimal:4', 'maximum_bonus_override' => 'decimal:2'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StaffBonusProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(StaffBonusProgram::class, 'staff_bonus_program_id');
    }
}
