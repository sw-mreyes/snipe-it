<?php

namespace Tests\Feature\Reservations;

use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class ReservationPagesTest extends TestCase
{
    public function testListPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertSee(route('api.reservations.index'), false);
    }

    public function testCalendarPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reservations.calendar'))
            ->assertOk()
            ->assertSee('reservations-calendar', false);
    }

    public function testCreateFormPreselectsAnAssetFromTheQueryString()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reservations.create', ['asset' => $asset->id]))
            ->assertOk()
            ->assertSee('value="'.$asset->id.'" selected', false);
    }

    public function testDetailPageShowsTheWindowAndAssets()
    {
        $asset = Asset::factory()->create(['name' => 'Roadshow laptop']);
        $reservation = Reservation::factory()->create(['name' => 'Autumn tour']);
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reservations.show', ['reservation' => $reservation->id]))
            ->assertOk()
            ->assertSee('Autumn tour')
            ->assertSee('Roadshow laptop');
    }

    public function testAssetViewListsUpcomingReservationsAndOffersToReserve()
    {
        $asset = Asset::factory()->create();
        $reservation = Reservation::factory()->create(['name' => 'Autumn tour']);
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', ['asset' => $asset->id]))
            ->assertOk()
            ->assertSee('Autumn tour')
            ->assertSee(route('reservations.create', ['asset' => $asset->id]), false);
    }

    public function testAssetViewDoesNotListPastReservations()
    {
        $asset = Asset::factory()->create();
        $reservation = Reservation::factory()->past()->create(['name' => 'Last summer']);
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', ['asset' => $asset->id]))
            ->assertOk()
            ->assertDontSee('Last summer');
    }

    public function testDetailPageFallsBackToTheAssetTagWhenTheAssetHasNoName()
    {
        $asset = Asset::factory()->create(['name' => '', 'asset_tag' => 'SW-000999']);
        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('reservations.show', ['reservation' => $reservation->id]))
            ->assertOk()
            ->assertSee('SW-000999');
    }

    public function testCheckoutFormWarnsAboutAnUpcomingReservationButStillAllowsCheckout()
    {
        $asset = Asset::factory()->create();
        $reservation = Reservation::factory()->create(['name' => 'Autumn tour']);
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.checkout.create', ['asset' => $asset->id]))
            // Warning shown...
            ->assertOk()
            ->assertSee(trans('reservations.next_reservation'))
            ->assertSee('Autumn tour')
            // ...and the checkout form is still there, not blocked.
            ->assertSee('name="assigned_user"', false);
    }

    public function testCheckoutFormIsUnchangedForAnUnreservedAsset()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.checkout.create', ['asset' => $asset->id]))
            ->assertOk()
            ->assertDontSee(trans('reservations.next_reservation'));
    }
}
