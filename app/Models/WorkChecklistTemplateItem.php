<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkChecklistTemplateItem extends Model
{
    protected $fillable = ['work_checklist_template_id', 'item_key', 'label', 'guidance', 'sort_order', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    /** @return BelongsTo<WorkChecklistTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkChecklistTemplate::class, 'work_checklist_template_id');
    }
}
