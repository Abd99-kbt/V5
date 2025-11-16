<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => $this->faker->company(),
            'name_ar' => 'شركة ' . $this->faker->company(),
            'province_en' => $this->faker->city(),
            'province_ar' => $this->faker->city(),
            'mobile_number' => $this->faker->phoneNumber(),
            'follow_up_person_en' => $this->faker->name(),
            'follow_up_person_ar' => $this->faker->name(),
            'address_en' => $this->faker->address(),
            'address_ar' => $this->faker->address(),
            'email' => $this->faker->unique()->safeEmail(),
            'tax_number' => $this->faker->unique()->numerify('#########'),
            'credit_limit' => $this->faker->numberBetween(5000, 50000),
            'customer_type' => $this->faker->randomElement(['individual', 'company']),
            'is_active' => true,
        ];
    }
}
