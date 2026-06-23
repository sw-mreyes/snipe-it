<?php

namespace Tests\Feature\Reservations\Ui;

use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class CreateReservationTest extends TestCase
{
    public function test_create_form_requires_view_permission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reservations.create'))
            ->assertForbidden();
    }

    public function test_store_requires_checkout_permission()
    {
        $this->actingAs(User::factory()->viewAssets()->create())
            ->post(route('reservations.store'), [
                'name' => 'Nope',
                'user_id' => User::factory()->create()->id,
                'start' => '2030-01-01 09:00:00',
                'end' => '2030-01-02 09:00:00',
                'assets' => [Asset::factory()->create()->id],
            ])
            ->assertForbidden();
    }

    public function test_can_create_reservation_via_ui()
    {
        $asset = Asset::factory()->create();
        $reserveFor = User::factory()->create();

        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('reservations.store'), [
                'name' => 'Loaner',
                'user_id' => $reserveFor->id,
                'start' => '2030-03-01 09:00:00',
                'end' => '2030-03-04 17:00:00',
                'assets' => [$asset->id],
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertDatabaseHas('sw_reservations', ['name' => 'Loaner', 'user_id' => $reserveFor->id]);
    }

    public function test_overlapping_reservation_is_rejected_with_errors()
    {
        $asset = Asset::factory()->create();
        $existing = Reservation::factory()->create([
            'start' => '2030-03-01 09:00:00',
            'end' => '2030-03-05 17:00:00',
        ]);
        $existing->assets()->attach($asset->id);

        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('reservations.store'), [
                'name' => 'Overlapping',
                'user_id' => User::factory()->create()->id,
                'start' => '2030-03-03 09:00:00',
                'end' => '2030-03-06 17:00:00',
                'assets' => [$asset->id],
            ])
            ->assertSessionHasErrors('assets');

        $this->assertDatabaseMissing('sw_reservations', ['name' => 'Overlapping']);
    }
}
