<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    protected $fillable = [
        'assessment_id',
        'model',
        'prompt_version',
        'response_json',
        'status',
        'error_message',
        'is_fallback',
        'reviewed_by',
        'reviewed_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'is_fallback'   => 'boolean',
            'reviewed_at'   => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
