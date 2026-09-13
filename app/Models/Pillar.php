<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pillar extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_version_id',
        'key',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'display_order',
        'weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight'    => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AssessmentVersion::class, 'assessment_version_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function pillarResults(): HasMany
    {
        return $this->hasMany(AssessmentPillarResult::class);
    }
}
