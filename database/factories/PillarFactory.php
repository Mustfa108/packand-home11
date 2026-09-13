<?php

namespace Database\Factories;

use App\Models\Pillar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pillar>
 */
class PillarFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            'key'            => 'axis_'.self::$sequence.'_'.strtolower($this->faker->unique()->lexify('???')),
            'name_ar'        => 'محور '.self::$sequence,
            'name_en'        => 'Axis '.self::$sequence,
            'description_ar' => 'وصف المحور',
            'description_en' => 'Axis description',
            'display_order'  => self::$sequence,
            'weight'         => 1.0,
            'is_active'      => true,
        ];
    }
}
