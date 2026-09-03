<?php

namespace App\Models;

use App\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_plan_id',
        'pillar_id',
        'phase',
        'phase_label_ar',
        'phase_label_en',
        'action_ar',
        'action_en',
        'ai_rephrased_ar',
        'ai_rephrased_en',
        'kpi_ar',
        'kpi_en',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActionItemStatus::class,
        ];
    }

    public function actionPlan(): BelongsTo
    {
        return $this->belongsTo(ActionPlan::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }
}
