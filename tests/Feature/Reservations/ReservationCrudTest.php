<?php

namespace Tests\Feature\Reservations;

use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class ReservationCrudTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Roadshow kit',
            'user_id' => User::factory()->create()->id,
            'start' => '2026-09-01 09:00',
            'end' => '2026-09-05 17:00',
            'notes' => 'For the autumn tour',
            'assets' => [Asset::factory()->create()->id],
        ], $overrides);
    }

    public function testReservationCanBeCreated()
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'user_id' => $user->id,
                'assets' => [$asset->id],
            ]))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $reservation = Reservation::first();

        $this->assertNotNull($reservation);
        $this->assertSame('Roadshow kit', $reservation->name);
        $this->assertSame($user->id, $reservation->user_id);
        $this->assertTrue($reservation->assets->contains($asset));
    }

    public function testDatetimeLocalFormatIsAccepted()
    {
        // Native datetime-local inputs submit "2026-09-01T09:00".
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'start' => '2026-09-01T09:00',
                'end' => '2026-09-05T17:00',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('reservations.index'));

        $this->assertSame('2026-09-01 09:00:00', Reservation::first()->start->format('Y-m-d H:i:s'));
    }

    public function testEndMustBeAfterStart()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'start' => '2026-09-05 09:00',
                'end' => '2026-09-01 09:00',
            ]))
            ->assertSessionHasErrors('end');

        $this->assertSame(0, Reservation::count());
    }

    public function testAtLeastOneAssetIsRequired()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload(['assets' => []]))
            ->assertSessionHasErrors('assets');
    }

    public function testOverlappingReservationForTheSameAssetIsRejected()
    {
        $asset = Asset::factory()->create();

        $existing = Reservation::factory()->between('2026-09-01 09:00', '2026-09-10 17:00')->create();
        $existing->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'assets' => [$asset->id],
                'start' => '2026-09-05 09:00',
                'end' => '2026-09-07 17:00',
            ]))
            ->assertSessionHasErrors('assets');

        $this->assertSame(1, Reservation::count());
    }

    public function testNonOverlappingReservationForTheSameAssetIsAllowed()
    {
        $asset = Asset::factory()->create();

        $existing = Reservation::factory()->between('2026-09-01 09:00', '2026-09-10 17:00')->create();
        $existing->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'assets' => [$asset->id],
                'start' => '2026-09-11 09:00',
                'end' => '2026-09-12 17:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Reservation::count());
    }

    public function testTheSameWindowIsAllowedForADifferentAsset()
    {
        $reserved = Asset::factory()->create();
        $other = Asset::factory()->create();

        $existing = Reservation::factory()->between('2026-09-01 09:00', '2026-09-10 17:00')->create();
        $existing->assets()->attach($reserved);

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), $this->payload([
                'assets' => [$other->id],
                'start' => '2026-09-01 09:00',
                'end' => '2026-09-10 17:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Reservation::count());
    }

    public function testReservationCanBeUpdatedWithoutConflictingWithItself()
    {
        $asset = Asset::factory()->create();
        $reservation = Reservation::factory()->between('2026-09-01 09:00', '2026-09-10 17:00')->create();
        $reservation->assets()->attach($asset);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('reservations.update', ['reservation' => $reservation->id]), $this->payload([
                'name' => 'Renamed',
                'user_id' => $reservation->user_id,
                'assets' => [$asset->id],
                'start' => '2026-09-02 09:00',
                'end' => '2026-09-09 17:00',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('reservations.index'));

        $this->assertSame('Renamed', $reservation->fresh()->name);
    }

    public function testUpdateDetachesDeselectedAssets()
    {
        $keep = Asset::factory()->create();
        $drop = Asset::factory()->create();

        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach([$keep->id, $drop->id]);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('reservations.update', ['reservation' => $reservation->id]), $this->payload([
                'user_id' => $reservation->user_id,
                'assets' => [$keep->id],
            ]))
            ->assertSessionHasNoErrors();

        $assets = $reservation->fresh()->assets;

        $this->assertTrue($assets->contains($keep));
        $this->assertFalse($assets->contains($drop));
    }

    public function testReservationCanBeDeleted()
    {
        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach(Asset::factory()->create());

        $this->actingAs(User::factory()->superuser()->create())
            ->delete(route('reservations.destroy', ['reservation' => $reservation->id]))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('sw_reservations', ['id' => $reservation->id]);
    }

    public function testUsersWithoutCheckoutPermissionCannotCreate()
    {
        $this->actingAs(User::factory()->viewAssets()->create())
            ->post(route('reservations.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Reservation::count());
    }

    public function testGuestsAreRedirectedToLogin()
    {
        User::factory()->create();

        $this->get(route('reservations.index'))->assertRedirect(route('login'));
    }
}
