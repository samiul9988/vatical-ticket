<?php

namespace Database\Factories;

use App\Models\NotificationSound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSound>
 */
class NotificationSoundFactory extends Factory
{
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'path' => 'sounds/'.fake()->uuid().'.mp3',
            'is_active' => false,
        ];
    }
}
