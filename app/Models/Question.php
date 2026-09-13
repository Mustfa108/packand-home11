<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_version_id',
        'pillar_id',
        'text_ar',
        'text_en',
        'display_order',
        'weight',
        'is_active',
        'used_in_assessments',
    ];

    protected function casts(): array
    {
        return [
            'weight'             => 'float',
            'is_active'          => 'boolean',
            'used_in_assessments' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AssessmentVersion::class, 'assessment_version_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }
}
