<?php

namespace Tests\Unit;

use App\Enums\ReadinessLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadinessLevelTest extends TestCase
{
    #[DataProvider('scoreProvider')]
    public function test_from_score_uses_official_thresholds(float $score, ReadinessLevel $expected): void
    {
        $this->assertSame($expected, ReadinessLevel::fromScore($score));
    }

    /**
     * @return array<string, array{0: float, 1: ReadinessLevel}>
     */
    public static function scoreProvider(): array
    {
        return [
            'zero is low' => [0, ReadinessLevel::LOW],
            '49.99 is low' => [49.99, ReadinessLevel::LOW],
            '50 is medium' => [50, ReadinessLevel::MEDIUM],
            '69.99 is medium' => [69.99, ReadinessLevel::MEDIUM],
            '70 is good' => [70, ReadinessLevel::GOOD],
            '100 is good' => [100, ReadinessLevel::GOOD],
        ];
    }
}
