<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = [
        'report_type',
        'format',
        'status',
        'requested_by',
        'filters',
        'progress',
        'row_count',
        'disk',
        'file_path',
        'started_at',
        'finished_at',
        'expires_at',
        'error_message',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportExportStatus::class,
            'filters' => 'array',
            'progress' => 'integer',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
