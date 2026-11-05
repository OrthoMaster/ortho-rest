<?php

namespace Database\Factories;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Restaurant> */
class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Quán '.fake()->unique()->word(),
            'slug' => 'quan-'.Str::lower(Str::random(8)),
            'status' => RestaurantStatus::Active,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RestaurantStatus::Locked]);
    }
}
