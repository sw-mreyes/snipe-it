<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Transformers\AssetsTransformer;
use App\Http\Transformers\ReservationsTransformer;
use App\Models\Asset;
use App\Models\Reservation;
use App\Services\Reservations\ReservationNotifier;
use Illuminate\Http\Request;

/**
 * API controller for the reservation system (custom fork feature).
 *
 * Authorization reuses the Asset permissions (read = view, write = checkout),
 * consistent with the web controller.
 */
class ReservationsController extends Controller
{
    /**
     * List reservations, with filtering, sorting and pagination.
     */
    public function index(Request $request)
    {
        $this->authorize('view', Asset::class);

        $reservations = Reservation::with('user', 'assets');

        $this->applyFilters($reservations, $request);

        $allowed_columns = ['id', 'name', 'start', 'end', 'created_at'];
        $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
        $sort = $request->input('sort');

        if ($sort === 'user.username') {
            // Select the base table's columns so the join cannot clobber them.
            $reservations->join('users', 'sw_reservations.user_id', '=', 'users.id')
                ->select('sw_reservations.*')
                ->orderBy('users.username', $order);
        } else {
            $reservations->orderBy(in_array($sort, $allowed_columns, true) ? $sort : 'start', $order);
        }

        $total = $reservations->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');

        $reservations = $reservations->skip($offset)->take($limit)->get();

        return (new ReservationsTransformer)->transformReservations($reservations, $total);
    }

    /**
     * Supported list filters.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('start_from')) {
            $query->where('start', '>=', $request->input('start_from'));
        }
        if ($request->filled('start_to')) {
            $query->where('start', '<=', $request->input('start_to'));
        }
        if ($request->filled('end_from')) {
            $query->where('end', '>=', $request->input('end_from'));
        }

        if ($request->filled('end_to')) {
            $query->where('end', '<=', $request->input('end_to'));
        } elseif (! $request->hasAny(['start_from', 'start_to', 'end_from'])) {
            // With no explicit range, only current and upcoming reservations are
            // relevant. A caller that asks for a range (the calendar requesting
            // its visible window) gets exactly that range instead.
            $query->where('end', '>=', now()->format('Y-m-d'));
        }

        if ($request->filled('start')) {
            $query->where('start', '=', $request->input('start'));
        }
        if ($request->filled('end')) {
            $query->where('end', '=', $request->input('end'));
        }
        if ($request->filled('user')) {
            $query->where('user_id', '=', $request->input('user'));
        }
        if ($request->filled('name_search')) {
            $query->where('name', 'LIKE', '%'.$request->input('name_search').'%');
        }
        if ($request->filled('note_search')) {
            $query->where('notes', 'LIKE', '%'.$request->input('note_search').'%');
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('notes', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function show(Reservation $reservation)
    {
        $this->authorize('view', Asset::class);

        return (new ReservationsTransformer)->transformReservation($reservation->load('user', 'assets'));
    }

    /**
     * Reservations that include a given asset.
     */
    public function forAsset($assetId)
    {
        $this->authorize('view', Asset::class);

        $reservations = Reservation::with('user', 'assets')
            ->forAsset($assetId)
            ->orderBy('start')
            ->get();

        return (new ReservationsTransformer)->transformReservations($reservations, $reservations->count());
    }

    /**
     * The assets attached to a reservation.
     */
    public function getAssets(Reservation $reservation)
    {
        $this->authorize('view', Asset::class);

        $assets = $reservation->assets;

        return (new AssetsTransformer)->transformAssets($assets, $assets->count());
    }

    public function store(StoreReservationRequest $request, ReservationNotifier $notifier)
    {
        $reservation = new Reservation;
        $reservation->fill($request->only(['name', 'user_id', 'start', 'end', 'notes']));

        if (! $reservation->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $reservation->getErrors()), 200);
        }

        $reservation->assets()->sync($request->input('assets'));

        $notifier->notifyPlaced($reservation);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            (new ReservationsTransformer)->transformReservation($reservation->load('user', 'assets')),
            trans('reservations.placed')
        ));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        $reservation->fill($request->only(['name', 'user_id', 'start', 'end', 'notes']));

        if (! $reservation->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $reservation->getErrors()), 200);
        }

        $reservation->assets()->sync($request->input('assets'));

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            (new ReservationsTransformer)->transformReservation($reservation->load('user', 'assets')),
            trans('reservations.updated')
        ));
    }

    public function destroy(Reservation $reservation)
    {
        $this->authorize('checkout', Asset::class);

        $reservation->assets()->detach();
        $reservation->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('reservations.deleted')));
    }
}
