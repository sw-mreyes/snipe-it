<?php

namespace App\Http\Controllers\Api;

use \App\Models\Reservation;
use \App\Models\Asset;
use \App\Models\User;
use \App\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ReservationsTransformer;
use App\Http\Transformers\AssetsTransformer;
use App\Notifications\ReservationPlacedNotification;
use Illuminate\Http\Request;
use App\Helpers\Helper;



class ReservationsController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::all();
        /*$offset = (($reservations) && (request('offset') > $reservations->count())) ? 0 : request('offset', 0);
        $limit = $request->input('limit', 50);
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $reservations->skip($offset)->take($limit)->orderBy('id', $order)->get();*/
        return (new ReservationsTransformer)->transformReservations($reservations, count($reservations));
    }

    public function show(Request $request)
    {
        return $this->index($request);
    }

    public function assets(Request $request, $reservationID)
    {
        $reservation = Reservation::where('id', '=', $reservationID)->first();
        if ($reservation) {
            $assets = $reservation->assets;
            return (new AssetsTransformer)->transformAssets($assets, count($assets));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.reservation_not_found')), 200);
    }

    /**
     * Get the reservations for a given asset.
     */
    public function assetReservations(Request $request)
    {
        $asset_id = $request->input('asset');

        if ($asset_id) {
            $entry = [
                'asset' => Asset::where('id', '=', $asset_id)->first(),
                'reservations' => array()
            ];
            $res =  Reservation::select('reservations.*')
                ->join('asset_reservation', 'reservations.id', '=', 'asset_reservation.reservation_id')
                ->where('asset_reservation.asset_id', '=', $asset_id)
                ->orderBy('start', 'asc')->get();
            foreach ($res as $reservation) {
                array_push($entry['reservations'], $reservation);
            }
            return (new ReservationsTransformer)->transformAssetReservation($entry);
        } else {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('hardware.message.not_found')), 200);
        }
    }

    public function calendar(Request $request)
    {
        $reservations = Reservation::whereNotNull('id');
        if ($request->input('start')) {
            $reservations->where('start', '>=', $request->input('start'));
        }
        if ($request->input('reservations')) {
            $reservations->whereIn('id', $request->input('reservations'));
        }
        //
        $reservations = $reservations->get();
        return (new ReservationsTransformer)->transformReservationsCalendar($reservations, count($reservations));
    }

    public function create(Request $request)
    {
        if ($res_id = $request->input('reservation')) {
            if (!$asset_id = $request->input('asset')) {
                return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.asset_required')), 200);
            }
            $asset = Asset::where('id', '=', $asset_id)->first();
            $reservation = Reservation::where('id', '=', $res_id)->first();
            if (!$asset) return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.asset_not_found')), 200);
            if (!$reservation) return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.reservation_not_found')), 200);

            if (!Helper::is_valid_timeframe($reservation->start, $reservation->end, [$asset->id], $reservation->id)) {
                return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.invalid_timeframe')), 200);
            }

            $reservation->assets()->save($asset);
            return (new ReservationsTransformer)->transformReservation($reservation);
        }



        $user = User::where('id', '=', $request->input('user'))->first();
        if (!$user) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.user_not_found')), 200);
        }
        if (!$request->input('name')) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.name_required')), 200);
        }
        if (!$request->input('start')) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.timeframe_required')), 200);
        }
        if (!$request->input('end')) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.timeframe_required')), 200);
        }

        if (!Helper::is_valid_timeframe($request->input('start'), $request->input('end'), [$request->input('asset')])) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.invalid_timeframe')), 200);
        }

        // If an asset was given, make sure it exists before creating the reservation.
        if ($asset_id = $request->input('asset')) {
            if (!$asset = Asset::where('id', '=', $asset_id)->first()) {
                return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.asset_not_found')), 200);
            }
        }

        $res = new Reservation();
        $res->name  = $request->input('name');
        $res->start = $request->input('start');
        $res->end   = $request->input('end');
        $res->notes = $request->input('notes');
        $res->user()->associate($user);
        $res->save();
        //

        if ($asset) {
            $res->assets()->save($asset);
        }

        return (new ReservationsTransformer)->transformReservation($res);
    }
}
