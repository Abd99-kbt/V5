<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => $this->faker->company() . ' Warehouse',
            'name_ar' => 'مستودع ' . $this->faker->company(),
            'code' => 'WH' . $this->faker->unique()->numberBetween(100, 999),
            'address_en' => $this->faker->address(),
            'address_ar' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'manager_name' => $this->faker->name(),
            'type' => $this->faker->randomElement(['main', 'scrap', 'sorting', 'custody']),
            'total_capacity' => $this->faker->numberBetween(1000, 10000),
            'used_capacity' => 0.00,
            'reserved_capacity' => 0.00,
            'is_active' => true,
            'is_main' => false,
            'accepts_transfers' => true,
            'requires_approval' => false,
        ];
    }
}
