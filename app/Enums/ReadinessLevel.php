<?php

namespace App\Enums;

enum ReadinessLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case GOOD = 'good';

    public function labelAr(): string
    {
        return match ($this) {
            self::LOW => 'منخفض',
            self::MEDIUM => 'متوسط',
            self::GOOD => 'جيد',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::GOOD => 'Good',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#DC2626',
            self::MEDIUM => '#F59E0B',
            self::GOOD => '#16A34A',
        };
    }

    /**
     * Official thresholds: 0–49 low, 50–69 medium, 70–100 good.
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score < 50 => self::LOW,
            $score < 70 => self::MEDIUM,
            default => self::GOOD,
        };
    }
}
