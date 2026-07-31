<?php

namespace Tests\Feature\Reservations;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationAssetExpectedCheckinNotification;
use App\Notifications\ReservationPlacedNotification;
use App\Services\Reservations\ReservationNotifier;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReservationNotificationTest extends TestCase
{
    public function testTheReservingUserIsNotified()
    {
        Notification::fake();

        $user = User::factory()->create();
        $reservation = Reservation::factory()->create(['user_id' => $user->id]);
        $reservation->assets()->attach(Asset::factory()->create());

        app(ReservationNotifier::class)->notifyPlaced($reservation);

        Notification::assertSentTo($user, ReservationPlacedNotification::class);
    }

    public function testTheCurrentHolderOfAReservedAssetIsNotified()
    {
        Notification::fake();

        $holder = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($holder)->create();

        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach($asset);

        app(ReservationNotifier::class)->notifyPlaced($reservation);

        Notification::assertSentTo($holder, ReservationAssetExpectedCheckinNotification::class);
    }

    public function testNobodyIsNotifiedForAnAssetThatIsNotCheckedOut()
    {
        Notification::fake();

        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach(Asset::factory()->create());

        app(ReservationNotifier::class)->notifyPlaced($reservation);

        Notification::assertNotSentTo(
            [$reservation->user],
            ReservationAssetExpectedCheckinNotification::class
        );
    }

    public function testAssetAssignedToALocationNotifiesThatLocationsManager()
    {
        Notification::fake();

        $manager = User::factory()->create();
        $location = Location::factory()->create(['manager_id' => $manager->id]);
        $asset = Asset::factory()->assignedToLocation($location)->create();

        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach($asset);

        app(ReservationNotifier::class)->notifyPlaced($reservation);

        Notification::assertSentTo($manager, ReservationAssetExpectedCheckinNotification::class);
    }

    public function testALocationWithoutAManagerWalksUpToItsParent()
    {
        Notification::fake();

        $manager = User::factory()->create();
        $parent = Location::factory()->create(['manager_id' => $manager->id]);
        $child = Location::factory()->create(['parent_id' => $parent->id, 'manager_id' => null]);

        $asset = Asset::factory()->assignedToLocation($child)->create();

        $reservation = Reservation::factory()->create();
        $reservation->assets()->attach($asset);

        app(ReservationNotifier::class)->notifyPlaced($reservation);

        Notification::assertSentTo($manager, ReservationAssetExpectedCheckinNotification::class);
    }

    public function testAFailingMailServerDoesNotBreakPlacingAReservation()
    {
        // The reservation is already saved by the time notifications go out, so
        // an unreachable mail server must not surface as an error to the user.
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP is down'));

        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), [
                'name' => 'Roadshow kit',
                'user_id' => $user->id,
                'start' => '2026-09-01 09:00',
                'end' => '2026-09-05 17:00',
                'assets' => [$asset->id],
            ])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, Reservation::count());
    }

    public function testPlacingAReservationSendsNotifications()
    {
        Notification::fake();

        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('reservations.store'), [
                'name' => 'Roadshow kit',
                'user_id' => $user->id,
                'start' => '2026-09-01 09:00',
                'end' => '2026-09-05 17:00',
                'assets' => [$asset->id],
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ReservationPlacedNotification::class);
    }
}
