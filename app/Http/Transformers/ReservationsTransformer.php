<?php

namespace App\Http\Transformers;

use \App\Models\Reservation;
use \App\Helpers\Helper;

class ReservationsTransformer
{
    public function transformReservations($reservations, $total)
    {
        $array = array();
        foreach ($reservations as $res) {
            $array[] = self::transformReservation($res);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformReservationsCalendar($reservations, $total)
    {
        $array = array();
        foreach ($reservations as $res) {
            $array[] = [
                'id' => (int) $res->id,
                'title' => $res->name,
                'category' => 'time',
                'start' => explode(' ', $res->start . '')[0],
                'end' => explode(' ', $res->end . '')[0],
                'attendees' => [$res->user->present()->fullName],
                'body' => $res->notes,
            ];
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformAssetReservation($entry)
    {
        return [
            'asset' => (new AssetsTransformer)->transformAsset($entry['asset']),
            'reservations' => $this->transformReservations($entry['reservations'], count($entry['reservations'])),
        ];
    }

    public function transformReservation(Reservation $res)
    {
        $array = [
            'id' => (int) $res->id,
            'name' => $res->name,
            'user' => [
                'id' => $res->user->id,
                'username' => $res->user->username,
                'full_name' => $res->user->present()->fullName
            ],
            'start' => Helper::getFormattedDateObject($res->start, 'datetime', false),
            'end' => Helper::getFormattedDateObject($res->end, 'datetime', false),
            'notes' => $res->notes,
            'created' => $res->created_at,
            'updated' => $res->updated_at,
            'assets' => $res->assets
        ];
        return $array;
    }
}
