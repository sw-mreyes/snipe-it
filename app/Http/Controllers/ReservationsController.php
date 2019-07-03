<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;
use stdClass;
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

    public function index()
    {
        $this->_authorize();
        return view('reservations/index');
    }

    public function calendar()
    {
        $this->_authorize();
        return view('reservations/calendar');
    }

    public function create()
    {
        $this->_authorize();
        $view = View::make('reservations/edit')
            ->with('item', new Reservation);
        return $view;
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

    public function store(Request $request)
    {
        $this->_authorize();

        if (!$user = User::find($request->input('user'))) return redirect('reservations')->with('error', trans('reservations.user_not_found'));
        if (!$request->input('name')) return redirect()->back()->with('error', trans('reservations.name_required'));
        if (!$request->input('start')) return redirect()->back()->with('error', trans('reservations.timeframe_required'));
        if (!$request->input('end')) return redirect()->back()->with('error', trans('reservations.timeframe_required'));

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

    public function update(Request $request)
    {
        $this->_authorize();

        $user = User::find($request->input('user'));
        $res = Reservation::where('id', '=', $request->input('reservation_id'))->first();
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


    public function show($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return view('reservations/view', [
            'reservation' => Reservation::where('id', '=', $reservationID)->first()
        ]);
    }

    public function edit($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return view('reservations/edit', [
            'item' => Reservation::where('id', '=', $reservationID)->first(),
        ]);
    }

    public function delete($reservationID)
    {
        $this->_authorize();
        $this->authorize('index', Asset::class);
        return redirect('reservations/index')->with('success', 'Delete message');
    }
}
