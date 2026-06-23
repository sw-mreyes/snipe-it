<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Asset;
use App\Models\Reservation;
use App\Services\ReservationNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web controller for the asset reservation system (custom fork feature).
 *
 * Per project decision, authorization reuses the existing Asset permissions
 * rather than introducing a dedicated reservation permission set: read actions
 * require the asset `view` permission, write actions the asset `checkout`
 * permission (the write requests also gate this via their authorize()).
 *
 * @version v2.0
 */
class ReservationsController extends Controller
{
    /**
     * Reservations list (table fed by the API).
     */
    public function index(): View
    {
        $this->authorize('view', Asset::class);

        $reservations = Reservation::with('user', 'assets')
            ->orderBy('start', 'asc')
            ->get();

        return view('reservations.index')->with('reservations', $reservations);
    }

    /**
     * Calendar view of reservations.
     */
    public function calendar(): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.calendar');
    }

    /**
     * Reservation create form. Optionally preselects an asset via ?asset=.
     */
    public function create(Request $request): View
    {
        $this->authorize('view', Asset::class);

        $forAsset = null;
        if ($request->filled('asset')) {
            $forAsset = Asset::find($request->input('asset'));
        }

        return view('reservations.edit')
            ->with('item', new Reservation)
            ->with('forAsset', $forAsset);
    }

    /**
     * Persist a new reservation.
     */
    public function store(StoreReservationRequest $request, ReservationNotifier $notifier): RedirectResponse
    {
        $reservation = new Reservation;
        $reservation->name = $request->input('name');
        $reservation->user_id = $request->input('user_id');
        $reservation->start = $request->input('start');
        $reservation->end = $request->input('end');
        $reservation->notes = $request->input('notes');

        if (! $reservation->save()) {
            return redirect()->back()->withInput()->withErrors($reservation->getErrors());
        }

        $reservation->assets()->sync($request->input('assets'));

        $notifier->notifyPlaced($reservation);

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.placed'));
    }

    /**
     * Show a single reservation.
     */
    public function show(Reservation $reservation): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.view')->with('reservation', $reservation);
    }

    /**
     * Reservation edit form.
     */
    public function edit(Reservation $reservation): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.edit')
            ->with('item', $reservation)
            ->with('forAsset', null);
    }

    /**
     * Persist updates to a reservation.
     */
    public function update(UpdateReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $reservation->name = $request->input('name');
        $reservation->user_id = $request->input('user_id');
        $reservation->start = $request->input('start');
        $reservation->end = $request->input('end');
        $reservation->notes = $request->input('notes');

        if (! $reservation->save()) {
            return redirect()->back()->withInput()->withErrors($reservation->getErrors());
        }

        $reservation->assets()->sync($request->input('assets'));

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.updated'));
    }

    /**
     * Delete a reservation.
     */
    public function destroy(Reservation $reservation): RedirectResponse
    {
        $this->authorize('checkout', Asset::class);

        $reservation->assets()->detach();
        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.deleted'));
    }
}
