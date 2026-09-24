<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'seats_total' => $this->faker->numberBetween(10, 200),
            'title' => $this->faker->sentence(3),
            'url_slug' => $this->faker->unique()->slug(),
            'start_time' => $this->faker->dateTimeBetween('+1 days', '+1 month'),
            'registration_start' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'registration_end' => $this->faker->dateTimeBetween('now', '+5 days'),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'contact_name' => $this->faker->name(),
            'bank_account' => $this->faker->iban('SK'),
            'location_id' => Location::factory(),
        ];
    }
}
