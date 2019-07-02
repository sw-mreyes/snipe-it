<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use \App\Models\Reservation;
use Illuminate\Http\Request;
use \App\Http\Transformers\ReservationsTransformer;
use App\Http\Transformers\AssetsTransformer;

class ReservationsController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::all();
        /*$offset = (($reservations) && (request('offset') > $reservations->count())) ? 0 : request('offset', 0);
        $limit = $request->input('limit', 50);
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $total = $reservations->count();
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
        return $reservationID;
    }
}
