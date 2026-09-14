<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_name',
        'org_type',
        'org_size',
        'team_member_count',
        'locale',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function expansionAreas(): HasMany
    {
        return $this->hasMany(ExpansionArea::class);
    }

    public function projectReviews(): HasMany
    {
        return $this->hasMany(ProjectReview::class);
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(Assessment::class)->latestOfMany()->where('status', 'completed');
    }

    public function getOrgTypeArAttribute(): string
    {
        return match ($this->org_type) {
            'civil_society'  => 'منظمة مجتمع مدني',
            'volunteer_team' => 'فريق تطوعي',
            'startup'        => 'مشروع ناشئ',
            'other'          => 'أخرى',
            default          => 'غير محدد',
        };
    }

    public function getOrgSizeArAttribute(): string
    {
        return match ($this->org_size) {
            'small'  => 'صغيرة',
            'medium' => 'متوسطة',
            'large'  => 'كبيرة',
            default  => 'غير محدد',
        };
    }
}
