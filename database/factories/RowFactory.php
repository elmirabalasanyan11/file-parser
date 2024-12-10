<?php

namespace Database\Factories;

use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

class RowFactory extends Factory
{
    protected $model = Row::class;

    public function definition()
    {
        return [
            'external_id' => $this->faker->unique()->numberBetween(1, 1000000),
            'name' => $this->faker->name(),
            'date' => $this->faker->date('Y-m-d'),
        ];
    }
}
