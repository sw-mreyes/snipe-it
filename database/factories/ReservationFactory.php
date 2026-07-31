<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'name' => $this->faker->words(3, true),
            'user_id' => User::factory(),
            'start' => $start,
            'end' => (clone $start)->modify('+2 days'),
            'notes' => $this->faker->sentence(),
        ];
    }

    /**
     * A reservation that is running right now.
     */
    public function current(): self
    {
        return $this->state(fn () => [
            'start' => now()->subDay(),
            'end' => now()->addDay(),
        ]);
    }

    /**
     * A reservation whose window has already closed.
     */
    public function past(): self
    {
        return $this->state(fn () => [
            'start' => now()->subWeeks(2),
            'end' => now()->subWeek(),
        ]);
    }

    /**
     * A reservation over an explicit window.
     */
    public function between($start, $end): self
    {
        return $this->state(fn () => [
            'start' => $start,
            'end' => $end,
        ]);
    }
}
