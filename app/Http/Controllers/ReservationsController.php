<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;
use View;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Asset;
use App\Models\User;
use App\Models\Location;
use App\Models\Setting;
use Notification;
use App\Notifications\ReservationPlacedNotification;
use App\Notifications\ReservationAssetExpectedCheckinNotification;

/**
 * Reservations Web route controller
 * 
 * Authentication currently uses Asset permissions.
 * 
 */
class ReservationsController extends Controller
{

    private function _authorize()
    {
        $this->authorize('index', Asset::class);
    }

    /**
     * Find the user 'responsible' for the given asset.
     * If the asset is not checked out, return null.
     * If its checked out to a user, return that user.
     * If its checked out to a location, find the manager for that location/a parent manager.
     * If its checked out to an asset, recursively search the user responsible for that asset.
     */
    private function find_responsible_user($asset)
    {
        if (!isset($asset)) return null;
        if ($asset->availableForCheckout()) return null;
        //
        $target = $asset->assignedTo;
        //       
        if ($asset->assigned_type == 'App\Models\User') {
            return $target;
        } else if ($asset->assigned_type == 'App\Models\Location') {
            // assigned to a location
            $loc = Location::where('id', '=', $target->id)->first();
            while (!$loc->manager) { // If the location does not hava a manager, traverse the parents.
                if ($loc->parent) {
                    $loc = $loc->parent;
                } else break;
            }
            // Will only return null if the top level does not have a manager.
            return $loc->manager ? $loc->manager : 'loc - not found :(';
        } else if ($asset->assigned_type == 'App\Models\Asset') {
            // assigned to an asset, find the user responsible for that asset.
            $target_asset = Asset::where('id', '=', $asset->assignedTo->id)->first();
            return $this->find_responsible_user($target_asset);
        } else {
            // this should never happen.
            return null;
        }
    }

    /**
     * Get the reservations for the given asset in the given timeframe.
     */
    private function get_reservations($start, $end, $asset_id, $reservationID = null)
    {
        return Reservation::select('reservations.*')
            ->join('asset_reservation', 'reservations.id', '=', 'asset_reservation.reservation_id')
            ->where('reservations.id', '!=', $reservationID)
            ->where(function ($qrx) use ($start, $end) {
                $qrx->where(function ($qry) use ($start, $end) {
                    $qry->where('end', '>=', $start)
                        ->where('end', '<=', $end);
                })->orWhere(function ($qry) use ($start, $end) {
                    $qry->where('start', '>=', $start)
                        ->where('start', '<=', $end);
                })->orWhere(function ($qry) use ($start, $end) {
                    $qry->where('start', '<=', $start)
                        ->where('end', '>=', $end);
                });
            })
            ->where('asset_reservation.asset_id', '=', $asset_id)
            ->orderBy('start', 'asc')->get();
    }

    /**
     * Check if the given reservation timeframe is valid (=>no other reservations) for the given list of asset(ids)
     */
    private function valid_timeframe($start, $end, $assets, $reservationID = null)
    {
        foreach ($assets as $asset_id) {
            $reservations = $this->get_reservations($start, $end, $asset_id, $reservationID);
            if (count($reservations) > 0) {
                return false;
            }
        }
        return true;
    }

    /**********************************************************************************
     * Public endpoints
     */

    /**
     * Get the reservations index page
     */
    public function index()
    {
        $this->_authorize();
        return view('reservations/index');
    }

    /**
     * TODO
     * Get the reservations calendar
     */
    public function calendar()
    {
        $this->_authorize();
        return view('reservations/calendar', [
            'reservations' => Reservation::all(),
        ]);
    }

    /**
     * Get the create reservation page
     */
    public function create(Request $request)
    {
        $this->_authorize();

        $view = View::make('reservations/edit')->with('item', new Reservation);
        if ($request->query('asset')) {
            if ($asset = Asset::where('id', '=', $request->query('asset'))->first()) {
                $view = $view->with('asset', $asset);
            }
        } else {
            // explicitly set asset to null so the templating engine does not complain.
            $view = $view->with('asset', null);
        }
        return $view;
    }

    /**
     * Store the create reservation data
     */
    public function store(Request $request)
    {
        $this->_authorize();

        if (!$user = User::find($request->input('user'))) return redirect('reservations')->with('error', trans('reservations.user_not_found'));
        if (!$request->input('name')) return redirect()->back()->with('error', trans('reservations.name_required'));
        if (!$request->input('start')) return redirect()->back()->with('error', trans('reservations.timeframe_required'));
        if (!$request->input('end')) return redirect()->back()->with('error', trans('reservations.timeframe_required'));

        if (!$this->valid_timeframe($request->input('start'), $request->input('end'), $request->input('assets'))) {
            return redirect()->back()->with('error', trans('reservations.invalid_timeframe'));
        }

        //return $this->get_reservations($request->input('start'), $request->input('end'), $request->input('assets'));

        $res = new Reservation();
        $res->name  = $request->input('name');
        $res->start = $request->input('start');
        $res->end   = $request->input('end');
        $res->notes = $request->input('notes');
        $res->user()->associate($user);

        $res->save();

        foreach ($request->input('assets') as $assetID) {
            $res->assets()->save(Asset::where('id', '=', $assetID)->first());
        }

        Notification::send(Setting::getSettings(), new ReservationPlacedNotification($res));
        foreach ($res->assets as $asset) {
            if ($responsible_user = $this->find_responsible_user($asset)) {
                // Send notification to the user that is currently responsible for the asset.
                // If the asset is checked out to a user, notify them. Otherwise, find the 
                // User that owns the asset / Manages the location that the asset is checked out to.
                Notification::send(Setting::getSettings(), new ReservationAssetExpectedCheckinNotification($res, $asset, $responsible_user));
            }
        }

        return redirect('reservations')->with('success', trans('reservations.placed'));
    }
    /**
     * Store the Update reservation data
     */
    public function update(Request $request)
    {
        $this->_authorize();



        $user = User::find($request->input('user'));
        $res = Reservation::where('id', '=', $request->input('reservation_id'))->first();

        // -- Check if new dates conflict with existing reservations
        $start = $request->input('start') ? $request->input('start') : $res->start;
        $end = $request->input('end') ? $request->input('end') : $res->end;
        $asset_ids = [];
        foreach ($res->assets as $asset) {
            array_push($asset_ids, $asset->id);
        }
        if (!$this->valid_timeframe($start, $end, $asset_ids, $res->id)) {
            return redirect()->back()->with('error', trans('reservations.invalid_timeframe'));
        }
        //
        $res->name  = $request->input('name');
        $res->start = $request->input('start');
        $res->end   = $request->input('end');
        $res->notes = $request->input('notes');
        $res->user()->associate($user);

        $res->save();


        foreach ($request->input('assets') as $assetID) {
            if (!$res->assets()->where('asset_id', '=', $assetID)->first()) {
                $res->assets()->save(Asset::where('id', '=', $assetID)->first());
            }
        }
        return redirect('reservations')->with('success', trans('reservations.updated'));
    }

    /**
     * Get the show reservation page for a given reservation.
     */
    public function show($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        if ($reservation = Reservation::where('id', '=', $reservationID)->first()) {
            return view('reservations/view', [
                'reservation' => Reservation::where('id', '=', $reservationID)->first()
            ]);
        } else {
            return redirect('reservations/index')->with('error', trans('reservations.reservation_not_found'));
        }
    }

    /**
     * Get the edit reservation page for a given reservation
     */
    public function edit($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        if ($reservation = Reservation::where('id', '=', $reservationID)->first()) {
            return view('reservations/edit', [
                'item' => $reservation,
            ]);
        } else {
            return redirect('reservations/index')->with('error', trans('reservations.reservation_not_found'));
        }
    }

    /**
     * Delete a reservation
     */
    public function delete($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        //return redirect('reservations/index')->with('success', trans('reservations.deleted'));
        return redirect('reservations/index')->with('success', 'Deletion not implemented.');
    }
}
