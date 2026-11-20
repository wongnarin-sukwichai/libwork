<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            // ไม่ใช้ safeEmail(): faker ภาษาไทยต้องมี extension intl จึงจะสร้างชื่ออีเมลได้
            'email' => fake()->unique()->numerify('user#####').'@example.test',
            'role' => 'member',
            'password' => 'password',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => 'staff']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }
}
