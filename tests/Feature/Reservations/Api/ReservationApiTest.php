<?php

namespace Tests\Feature\Reservations\Api;

use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    public function test_store_requires_checkout_permission()
    {
        $this->actingAsForApi(User::factory()->viewAssets()->create())
            ->postJson(route('api.reservations.store'), [
                'name' => 'Nope',
                'user_id' => User::factory()->create()->id,
                'start' => '2030-01-01 09:00:00',
                'end' => '2030-01-02 09:00:00',
                'assets' => [Asset::factory()->create()->id],
            ])
            ->assertForbidden();
    }

    public function test_can_store_reservation()
    {
        $asset = Asset::factory()->create();
        $reserveFor = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutAssets()->create())
            ->postJson(route('api.reservations.store'), [
                'name' => 'Conference loaner',
                'user_id' => $reserveFor->id,
                'start' => '2030-01-01 09:00:00',
                'end' => '2030-01-05 17:00:00',
                'notes' => 'For the offsite',
                'assets' => [$asset->id],
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('sw_reservations', [
            'name' => 'Conference loaner',
            'user_id' => $reserveFor->id,
        ]);

        $reservation = Reservation::firstWhere('name', 'Conference loaner');
        $this->assertDatabaseHas('sw_asset_reservation', [
            'asset_id' => $asset->id,
            'reservation_id' => $reservation->id,
        ]);
    }

    public function test_rejects_overlapping_reservation_for_same_asset()
    {
        $asset = Asset::factory()->create();
        $existing = Reservation::factory()->create([
            'start' => '2030-01-01 09:00:00',
            'end' => '2030-01-05 17:00:00',
        ]);
        $existing->assets()->attach($asset->id);

        $this->actingAsForApi(User::factory()->checkoutAssets()->create())
            ->postJson(route('api.reservations.store'), [
                'name' => 'Overlapping',
                'user_id' => User::factory()->create()->id,
                'start' => '2030-01-04 09:00:00',
                'end' => '2030-01-07 17:00:00',
                'assets' => [$asset->id],
            ])
            ->assertStatusMessageIs('error');

        $this->assertDatabaseMissing('sw_reservations', ['name' => 'Overlapping']);
    }

    public function test_index_lists_reservations()
    {
        $asset = Asset::factory()->create();
        $reservation = Reservation::factory()->create([
            'start' => '2035-02-01 09:00:00',
            'end' => '2035-02-03 17:00:00',
        ]);
        $reservation->assets()->attach($asset->id);

        $this->actingAsForApi(User::factory()->viewAssets()->create())
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->assertJsonStructure(['total', 'rows']);
    }

    public function test_index_requires_view_permission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.reservations.index'))
            ->assertForbidden();
    }
}
