<?php

namespace Tests\Feature\Reservations;

use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    public function testIndexReturnsCurrentAndUpcomingByDefault()
    {
        $upcoming = Reservation::factory()->create(['name' => 'Upcoming one']);
        $past = Reservation::factory()->past()->create(['name' => 'Past one']);

        $rows = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->json('rows');

        $names = array_column($rows, 'name');

        $this->assertContains('Upcoming one', $names);
        $this->assertNotContains('Past one', $names);
    }

    public function testAnExplicitRangeOptsOutOfTheUpcomingOnlyDefault()
    {
        // This is what makes the calendar able to show past months.
        $past = Reservation::factory()->past()->create(['name' => 'Past one']);

        $rows = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index', [
                'start_to' => now()->addYear()->format('Y-m-d'),
                'end_from' => now()->subYear()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->json('rows');

        $this->assertContains('Past one', array_column($rows, 'name'));
    }

    public function testRowsCarryBothDisplayAndIsoDates()
    {
        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach(Asset::factory()->create());

        $row = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->json('rows.0');

        // Display objects drive the table; ISO drives the calendar.
        $this->assertArrayHasKey('formatted', $row['start']);
        $this->assertNotEmpty($row['start_iso']);
        $this->assertNotEmpty($row['end_iso']);
        $this->assertContains($row['status'], ['active', 'upcoming', 'past']);
    }

    public function testAssetRowsCarryANameLabel()
    {
        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach(Asset::factory()->create(['name' => 'Roadshow laptop']));

        $row = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->json('rows.0');

        $this->assertSame('Roadshow laptop', $row['assets'][0]['label']);
    }

    public function testAssetLabelFallsBackToTheTagWhenTheAssetHasNoName()
    {
        $reservation = Reservation::factory()->create();
        $asset = Asset::factory()->create(['name' => '', 'asset_tag' => 'SW-000123']);
        $reservation->assets()->attach($asset);

        $row = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->json('rows.0');

        $this->assertSame('SW-000123', $row['assets'][0]['label']);
    }

    public function testSortingByUsernameDoesNotClobberTheReservationColumns()
    {
        // The join would otherwise bleed users.id into the model.
        Reservation::factory()->count(2)->create();

        $rows = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index', ['sort' => 'user.username', 'order' => 'asc']))
            ->assertOk()
            ->json('rows');

        foreach ($rows as $row) {
            $this->assertNotNull($row['id']);
            $this->assertNotEmpty($row['name']);
        }
    }

    public function testForAssetReturnsOnlyThatAssetsReservations()
    {
        $asset = Asset::factory()->create();
        $other = Asset::factory()->create();

        $mine = Reservation::factory()->create(['name' => 'Mine']);
        $mine->assets()->attach($asset);

        $theirs = Reservation::factory()->create(['name' => 'Theirs']);
        $theirs->assets()->attach($other);

        $rows = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.forasset', ['asset_id' => $asset->id]))
            ->assertOk()
            ->json('rows');

        $names = array_column($rows, 'name');

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    public function testSearchFilterMatchesNameAndNotes()
    {
        Reservation::factory()->create(['name' => 'Zetta tour', 'notes' => 'nothing']);
        Reservation::factory()->create(['name' => 'Other', 'notes' => 'Zetta in the notes']);
        Reservation::factory()->create(['name' => 'Unrelated', 'notes' => 'nothing']);

        $rows = $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.reservations.index', ['search' => 'Zetta']))
            ->assertOk()
            ->json('rows');

        $this->assertCount(2, $rows);
    }

    public function testReservationCanBeCreatedThroughTheApi()
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.reservations.store'), [
                'name' => 'From the API',
                'user_id' => $user->id,
                'start' => '2026-09-01 09:00',
                'end' => '2026-09-05 17:00',
                'assets' => [$asset->id],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(1, Reservation::where('name', 'From the API')->count());
    }

    public function testApiRejectsAnOverlappingWindow()
    {
        $asset = Asset::factory()->create();

        $existing = Reservation::factory()->between('2026-09-01 09:00', '2026-09-10 17:00')->create();
        $existing->assets()->attach($asset);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.reservations.store'), [
                'name' => 'Clashing',
                'user_id' => User::factory()->create()->id,
                'start' => '2026-09-02 09:00',
                'end' => '2026-09-03 17:00',
                'assets' => [$asset->id],
            ])
            // Snipe-IT answers API validation failures with HTTP 200 and an
            // error payload (see Handler::invalidJson), not a 422.
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('messages.assets.0', trans('reservations.invalid_timeframe'));

        $this->assertSame(1, Reservation::count());
    }

    public function testReservationCanBeDeletedThroughTheApi()
    {
        $reservation = Reservation::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.reservations.destroy', ['reservation' => $reservation->id]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('sw_reservations', ['id' => $reservation->id]);
    }

    public function testUsersWithoutAssetViewPermissionCannotList()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.reservations.index'))
            ->assertForbidden();
    }
}
