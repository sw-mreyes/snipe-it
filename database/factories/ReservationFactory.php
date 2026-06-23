<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $start = Carbon::now()->addDays($this->faker->numberBetween(1, 30));

        return [
            'name' => $this->faker->sentence(3),
            'user_id' => User::factory(),
            'start' => $start,
            'end' => (clone $start)->addDays($this->faker->numberBetween(1, 7)),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * A reservation whose window has already ended.
     */
    public function past()
    {
        return $this->state(function () {
            $start = Carbon::now()->subDays($this->faker->numberBetween(8, 30));

            return [
                'start' => $start,
                'end' => (clone $start)->addDays($this->faker->numberBetween(1, 7)),
            ];
        });
    }
}
