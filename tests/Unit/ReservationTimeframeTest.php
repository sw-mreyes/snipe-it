<?php

namespace Tests\Unit;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Reservation;
use Tests\TestCase;

class ReservationTimeframeTest extends TestCase
{
    private function reservationForAsset(Asset $asset, string $start, string $end): Reservation
    {
        $reservation = Reservation::factory()->create([
            'start' => $start,
            'end' => $end,
        ]);
        $reservation->assets()->attach($asset->id);

        return $reservation;
    }

    public function test_rejects_window_where_start_is_not_before_end()
    {
        $asset = Asset::factory()->create();

        $this->assertFalse(Helper::is_valid_timeframe('2030-01-10 10:00:00', '2030-01-10 10:00:00', [$asset->id]));
        $this->assertFalse(Helper::is_valid_timeframe('2030-01-11 10:00:00', '2030-01-10 10:00:00', [$asset->id]));
    }

    public function test_allows_non_overlapping_window_for_same_asset()
    {
        $asset = Asset::factory()->create();
        $this->reservationForAsset($asset, '2030-01-01 09:00:00', '2030-01-05 17:00:00');

        $this->assertTrue(Helper::is_valid_timeframe('2030-01-06 09:00:00', '2030-01-08 17:00:00', [$asset->id]));
    }

    public function test_rejects_overlapping_window_for_same_asset()
    {
        $asset = Asset::factory()->create();
        $this->reservationForAsset($asset, '2030-01-01 09:00:00', '2030-01-05 17:00:00');

        // overlaps the tail of the existing reservation
        $this->assertFalse(Helper::is_valid_timeframe('2030-01-04 09:00:00', '2030-01-07 17:00:00', [$asset->id]));
    }

    public function test_allows_overlap_for_a_different_asset()
    {
        $reserved = Asset::factory()->create();
        $other = Asset::factory()->create();
        $this->reservationForAsset($reserved, '2030-01-01 09:00:00', '2030-01-05 17:00:00');

        $this->assertTrue(Helper::is_valid_timeframe('2030-01-02 09:00:00', '2030-01-04 17:00:00', [$other->id]));
    }

    public function test_excludes_the_reservation_being_updated_from_its_own_conflict_check()
    {
        $asset = Asset::factory()->create();
        $reservation = $this->reservationForAsset($asset, '2030-01-01 09:00:00', '2030-01-05 17:00:00');

        // Same window, but excluding itself -> should be considered valid.
        $this->assertTrue(
            Helper::is_valid_timeframe('2030-01-01 09:00:00', '2030-01-05 17:00:00', [$asset->id], $reservation->id)
        );

        // Without the exclusion it conflicts with itself.
        $this->assertFalse(
            Helper::is_valid_timeframe('2030-01-01 09:00:00', '2030-01-05 17:00:00', [$asset->id])
        );
    }

    public function test_soft_deleted_reservations_do_not_block()
    {
        $asset = Asset::factory()->create();
        $reservation = $this->reservationForAsset($asset, '2030-01-01 09:00:00', '2030-01-05 17:00:00');
        $reservation->delete();

        $this->assertTrue(Helper::is_valid_timeframe('2030-01-02 09:00:00', '2030-01-04 17:00:00', [$asset->id]));
    }

    public function test_next_reservation_returns_the_soonest_upcoming_reservation()
    {
        $asset = Asset::factory()->create();
        $later = $this->reservationForAsset($asset, '2030-03-01 09:00:00', '2030-03-05 17:00:00');
        $sooner = $this->reservationForAsset($asset, '2030-02-01 09:00:00', '2030-02-05 17:00:00');

        $next = Reservation::nextReservationFor($asset->id);

        $this->assertNotNull($next);
        $this->assertSame($sooner->id, $next->id);
    }

    public function test_next_reservation_ignores_past_reservations()
    {
        $asset = Asset::factory()->create();
        $this->reservationForAsset($asset, '2000-01-01 09:00:00', '2000-01-05 17:00:00');

        $this->assertNull(Reservation::nextReservationFor($asset->id));
    }
}
