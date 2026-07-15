<?php

namespace Database\Factories;

use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'colour' => $this->faker->randomElement(Label::COLOURS),
        ];
    }
}
