<?php

namespace App\Models;

use App\Enums\ReadinessLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'overall_score',
        'readiness_level',
        'ai_summary_ar',
        'ai_generated_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated_at' => 'datetime',
            'overall_score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function pillarResults(): HasMany
    {
        return $this->hasMany(AssessmentPillarResult::class);
    }

    public function actionPlan(): HasOne
    {
        return $this->hasOne(ActionPlan::class);
    }

    public function getReadinessLevelArAttribute(): string
    {
        return ReadinessLevel::tryFrom((string) $this->readiness_level)?->labelAr() ?? 'غير محدد';
    }

    public function getReadinessLevelEnAttribute(): string
    {
        return ReadinessLevel::tryFrom((string) $this->readiness_level)?->labelEn() ?? 'Unknown';
    }

    public function getReadinessColorAttribute(): string
    {
        return ReadinessLevel::tryFrom((string) $this->readiness_level)?->color() ?? '#6B7280';
    }

    public function getAiReadyAttribute(): bool
    {
        return ! is_null($this->ai_generated_at);
    }

    public function getPdfReadyAttribute(): bool
    {
        return ! is_null($this->pdf_path);
    }
}
