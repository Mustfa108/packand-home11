<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_name',
        'stage',
        'location',
        'team_size',
        'annual_budget',
        'mission',
        'problem',
        'beneficiaries',
        'activities',
        'impact',
        'sustainability',
        'ai_score',
        'ai_level',
        'ai_summary_ar',
        'ai_strengths',
        'ai_risks',
        'ai_recommendations',
        'ai_kpis',
        'ai_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'annual_budget' => 'float',
            'ai_score' => 'integer',
            'ai_strengths' => 'array',
            'ai_risks' => 'array',
            'ai_recommendations' => 'array',
            'ai_kpis' => 'array',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectReviewChatMessage::class);
    }
}