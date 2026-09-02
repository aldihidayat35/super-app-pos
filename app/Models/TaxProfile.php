<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxProfile extends Model
{
    protected $fillable = [
        'scope_key', 'work_location_id', 'legal_name', 'tax_number', 'nitku', 'tax_address', 'is_pkp',
        'pkp_effective_date', 'signatory_name', 'signatory_tax_number', 'calculation_enabled',
        'default_output_tax_rule_id', 'default_input_tax_rule_id', 'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pkp' => 'boolean',
            'pkp_effective_date' => 'date',
            'calculation_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<TaxRule, $this> */
    public function defaultOutputRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'default_output_tax_rule_id');
    }

    /** @return BelongsTo<TaxRule, $this> */
    public function defaultInputRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'default_input_tax_rule_id');
    }
}
