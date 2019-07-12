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
    /**
     * Used in all API methods.
     * currently just checks Asset index permissions.
     * Maybe introduce new policy in the future?
     */
    private function _authorize()
    {
        $this->authorize('index', Asset::class);
    }

    /**
     * Get reservations.
     * 
     * Parameters:
     *  Only get reservations ..
     * 
     *  - [start/end]_from : .. that start/end at or after this date.
     *  - [start/end]_to   : .. that start/end at or before this. 
     *  - [start/end]      : .. that start/end exactly at the given datetime
     *  - user             : .. that are for the given user (id)
     *  - note_search      : .. where the note contains the given search term.
     * 
     * Limit the results:
     * - offset     : skip this many elements of the result
     * - limit      : limit the result set size
     * - order_by   : order by this column
     * - order      : asc[ending] or desc[ending]
     */
    private function index_query($request)
    {
        $this->_authorize();

        $reservations = Reservation::select('reservations.*');

        // from <= start <= to
        if ($request->input('start_from')) {
            $reservations->where('start', '>=', $request->input('start_from'));
        }
        if ($request->input('start_to')) {
            $reservations->where('start', '<=', $request->input('start_to'));
        }
        // from <= end <= to
        if ($request->input('end_from')) {
            $reservations->where('end', '>=', $request->input('end_from'));
        }
        if ($request->input('end_to')) {
            $reservations->where('end', '<=', $request->input('end_to'));
        }
        //
        if ($request->input('end')) {
            $reservations->where('end', '=', $request->input('end'));
        }
        if ($request->input('start')) {
            $reservations->where('start', '=', $request->input('start'));
        }
        // user
        if ($request->input('user')) {
            $reservations->where('user_id', '=', $request->input('user'));
        }
        // note search
        if ($request->input('note_search')) {
            $term = $request->input('note_search');
            $reservations->where('notes', 'LIKE', "%{$term}%");
        }
        // name search
        if ($request->input('name_search')) {
            $term = $request->input('name_search');
            $reservations->where('name', 'LIKE', "%{$term}%");
        }
        // Notes OR Name search
        if ($request->input('search')) {
            $term = $request->input('search');
            $reservations->where(function ($qrx) use ($term) {
                $qrx->where('name', 'LIKE', "%{$term}%");
                $qrx->orWhere('notes', 'LIKE', "%{$term}%");
            });
        }
        // --
        // Offset
        if ($request->input('offset')) {
            $off = (int) $request->input('offset');
            if ($off < $reservations->count()) {
                $reservations->skip($off);
            }
        }
        // Limit -- default 1000
        $limit = $request->input('limit', 1000);
        $reservations->take($limit);
        // Order by (sort & order)
        if ($request->input('sort')) {
            $ord = $request->input('order');
            $order_type = $ord ? $ord : 'asc';
            $sort_column  = $request->input('sort');

            // Only join w/ users table if we want to sort by username.
            if (strcmp($sort_column, 'user.username') == 0) {
                $reservations->join('users', 'reservations.user_id', '=', 'users.id');
                $sort_column = 'users.username';
            }
            $reservations->orderBy($sort_column, $order_type);
        }
        //
        return $reservations;
    }

    /**
     * Get reservations.
     * 
     */
    public function index(Request $request)
    {
        $this->_authorize();
        $reservations = $this->index_query($request)->get();
        return (new ReservationsTransformer)->transformReservations($reservations, count($reservations));
    }


    public function show(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Get the reservations for a given asset.
     */
    public function assetReservations(Request $request)
    {
        $this->_authorize();
        $asset_id = $request->input('asset');

        if ($asset_id) {
            $entry = [
                'asset' => Asset::where('id', '=', $asset_id)->first(),
                'reservations' => array()
            ];
            $res =  Reservation::select('reservations.*')
                ->join('asset_reservation', 'reservations.id', '=', 'asset_reservation.reservation_id')
                ->where('asset_reservation.asset_id', '=', $asset_id)
                ->orderBy('start', 'asc')->distinct('reservations.id')->get();
            foreach ($res as $reservation) {
                array_push($entry['reservations'], $reservation);
            }
            return (new ReservationsTransformer)->transformAssetReservation($entry);
        } else {
            //return response()->json(Helper::formatStandardApiResponse('error', null, trans('hardware.message.not_found')), 200);
            return response()->json(Helper::formatStandardApiResponse('error', null, "WTF"), 200);
        }
    }

    /**
     * Get reservations and return a JSON result usable by the TUI Calendar.
     */
    public function calendar(Request $request)
    {
        $this->_authorize();
        $reservations = $this->index_query($request);
        if ($request->input('reservations')) {
            $reservations->whereIn('id', $request->input('reservations'));
        }
        //
        $reservations = $reservations->get();
        return (new ReservationsTransformer)->transformReservationsCalendar($reservations, count($reservations));
    }

    /**
     * Create a Reservation.
     */
    public function create(Request $request)
    {
        $this->_authorize();
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

        //
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

        return response()->json(Helper::formatStandardApiResponse('success', (new ReservationsTransformer)->transformReservation($res), trans('reservations.placed'), 200));
    }

    /**
     * Update a given reservation
     */
    public function update(Request $request, $reservationID)
    {
        $this->_authorize();
        //
        if (!$res = Reservation::where('id', '=', $reservationID)->first()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.reservation_not_found')), 200);
        }
        //
        $usr = $request->input('user') ? User::where('id', '=', $request->input('user'))->first() : $res->user;
        if (!$usr) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.user_not_found')), 200);
        }
        //
        $start  = $request->input('start') ? $request->input('start')   : $res->start;
        $end    = $request->input('end')   ? $request->input('end')     : $res->end;
        //
        $asset_ids = array();
        foreach ($res->assets as $asset) {
            array_push($asset_ids, $asset->id);
        }
        if ($request->input('asset')) {
            if (!Asset::where('id', '=', $request->input('asset'))->first()) {
                return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.asset_not_found')), 200);
            }
            array_push($asset_ids, $request->input('asset'));
        }
        //
        if (!Helper::is_valid_timeframe($start, $end, $asset_ids, $res->id)) {
            return response()->json(Helper::formatStandardApiResponse('error', [
                'start' => $start,
                'end' => $end,
            ], trans('reservations.invalid_timeframe')), 200);
        }
        //
        $name   = $request->input('name')   ? $request->input('name')  : $res->name;
        $notes  = $request->input('notes')  ? $request->input('notes') : $res->notes;

        // Store it all
        $res->name  = $name;
        $res->start = $start;
        $res->end   = $end;
        $res->notes = $notes;
        $res->user()->associate($usr);
        $res->save();
        //
        if ($request->input('asset')) {
            $asset = Asset::where('id', '=', $request->input('asset'))->first();
            $res->assets()->save($asset);
        }
        //
        return response()->json(Helper::formatStandardApiResponse('success', (new ReservationsTransformer)->transformReservation($res), trans('reservations.updated')), 200);
    }

    /**
     * Get a reservation by ID
     */
    public function by_id(Request $request, $reservationID)
    {
        $this->_authorize();
        if (!$reservation = Reservation::where('id', '=', $reservationID)->first()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.reservation_not_found')), 200);
        }
        return response()->json(Helper::formatStandardApiResponse('success', (new ReservationsTransformer)->transformReservation($reservation), 200));
    }

    /**
     * Get all assets reserved by a given Reservation.
     */
    public function get_assets(Request $request, $reservationID)
    {
        $this->_authorize();
        if (!$reservation = Reservation::where('id', '=', $reservationID)->first()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('reservations.reservation_not_found')), 200);
        }
        $assets = $reservation->assets;

        return (new AssetsTransformer)->transformAssets($assets, count($assets));
    }
}
