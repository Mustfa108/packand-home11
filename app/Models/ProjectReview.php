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
        'lat',
        'lng',
        'is_claimed',
        'is_public_on_map',
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
        'ai_full_summary_ar',
        'ai_strengths',
        'ai_risks',
        'ai_recommendations',
        'ai_kpis',
        'ai_features',
        'ai_goals',
        'ai_how_it_works',
        'ai_ideal_steps',
        'ai_generated_at',
        'ai_is_fallback',
    ];

    protected function casts(): array
    {
        return [
            'annual_budget' => 'float',
            'lat' => 'float',
            'lng' => 'float',
            'is_claimed' => 'boolean',
            'is_public_on_map' => 'boolean',
            'ai_score' => 'integer',
            'ai_strengths' => 'array',
            'ai_risks' => 'array',
            'ai_recommendations' => 'array',
            'ai_kpis' => 'array',
            'ai_features' => 'array',
            'ai_goals' => 'array',
            'ai_how_it_works' => 'array',
            'ai_ideal_steps' => 'array',
            'ai_generated_at' => 'datetime',
            'ai_is_fallback' => 'boolean',
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
