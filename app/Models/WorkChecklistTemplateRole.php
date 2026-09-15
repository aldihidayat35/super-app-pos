<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkChecklistTemplateRole extends Model
{
    public $timestamps = false;

    protected $fillable = ['work_checklist_template_id', 'role_name'];

    /** @return BelongsTo<WorkChecklistTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkChecklistTemplate::class, 'work_checklist_template_id');
    }
}
