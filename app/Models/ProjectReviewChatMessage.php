<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReviewChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_review_id',
        'role',
        'content',
    ];

    public function projectReview(): BelongsTo
    {
        return $this->belongsTo(ProjectReview::class);
    }
}