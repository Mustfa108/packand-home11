<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pillar extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'display_order',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function pillarResults(): HasMany
    {
        return $this->hasMany(AssessmentPillarResult::class);
    }
}
