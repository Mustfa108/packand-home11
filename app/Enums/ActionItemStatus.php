<?php

namespace App\Enums;

enum ActionItemStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';

    public function labelAr(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'لم تبدأ',
            self::IN_PROGRESS => 'قيد التنفيذ',
            self::COMPLETED => 'مكتملة',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Not started',
            self::IN_PROGRESS => 'In progress',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
