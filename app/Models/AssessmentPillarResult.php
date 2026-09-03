<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPillarResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'pillar_id',
        'raw_score',
        'max_score',
        'percentage',
        'is_weak',
    ];

    protected function casts(): array
    {
        return [
            'is_weak'    => 'boolean',
            'percentage' => 'float',
            'raw_score'  => 'float',
            'max_score'  => 'float',
        ];
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
