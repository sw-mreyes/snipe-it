<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Asset;
use App\Models\Reservation;
use App\Services\Reservations\ReservationNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web controller for the asset reservation system (custom fork feature).
 *
 * Authorization reuses the existing Asset permissions rather than introducing a
 * reservation permission set: reads require the asset `view` permission, writes
 * the asset `checkout` permission (the write requests gate this too).
 */
class ReservationsController extends Controller
{
    /**
     * Reservation list. Rows come from the API.
     */
    public function index(): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.index');
    }

    /**
     * Calendar view. Events come from the API.
     */
    public function calendar(): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.calendar');
    }

    /**
     * Create form. `?asset=` preselects an asset, so the asset page can link
     * straight into a reservation for that item.
     */
    public function create(Request $request): View
    {
        $this->authorize('checkout', Asset::class);

        return view('reservations.edit')
            ->with('item', new Reservation)
            ->with('forAsset', $request->filled('asset') ? Asset::find($request->input('asset')) : null);
    }

    public function store(StoreReservationRequest $request, ReservationNotifier $notifier): RedirectResponse
    {
        $reservation = new Reservation;
        $reservation->fill($request->only(['name', 'user_id', 'start', 'end', 'notes']));

        if (! $reservation->save()) {
            return redirect()->back()->withInput()->withErrors($reservation->getErrors());
        }

        $reservation->assets()->sync($request->input('assets'));

        $notifier->notifyPlaced($reservation);

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.placed'));
    }

    public function show(Reservation $reservation): View
    {
        $this->authorize('view', Asset::class);

        return view('reservations.view')
            ->with('reservation', $reservation->load('user', 'assets'));
    }

    public function edit(Reservation $reservation): View
    {
        $this->authorize('checkout', Asset::class);

        return view('reservations.edit')
            ->with('item', $reservation)
            ->with('forAsset', null);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $reservation->fill($request->only(['name', 'user_id', 'start', 'end', 'notes']));

        if (! $reservation->save()) {
            return redirect()->back()->withInput()->withErrors($reservation->getErrors());
        }

        // sync() so deselecting an asset actually detaches it.
        $reservation->assets()->sync($request->input('assets'));

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.updated'));
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        $this->authorize('checkout', Asset::class);

        $reservation->assets()->detach();
        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', trans('reservations.deleted'));
    }
}
