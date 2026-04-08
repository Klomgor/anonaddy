<?php

namespace Database\Factories;

use App\Models\BlockedSender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockedSenderFactory extends Factory
{
    protected $model = BlockedSender::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['email', 'domain']);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'value' => $type === 'email'
                ? $this->faker->unique()->safeEmail()
                : $this->faker->unique()->domainName(),
        ];
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'value' => $this->faker->unique()->safeEmail(),
        ]);
    }

    public function domain(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'domain',
            'value' => $this->faker->unique()->domainName(),
        ]);
    }
}
