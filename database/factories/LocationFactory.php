<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'address' => $this->faker->address(),
            'svg_map' => '/sedenie/seatmap.svg',
            'places_total' => $this->faker->numberBetween(20, 400),
        ];
    }
}

