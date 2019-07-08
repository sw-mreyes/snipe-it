<?php

namespace App\Http\Transformers;

use \App\Models\Reservation;

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
            $array[] = self::transformReservationCalendar($res);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformReservation(Reservation $res)
    {
        $array = [
            'id' => (int) $res->id,
            'name' => $res->name,
            'user' => [
                'id' => $res->user->id,
                'username' => $res->user->username,
                'full_name' => $res->user->name
            ],
            'start' => $res->start,
            'end' => $res->end,
            'notes' => $res->notes,
            'created' => $res->created_at,
            'updated' => $res->updated_at,
            'assets' => (int) count($res->assets)
        ];
        return $array;
    }

    /**
     * Generate random html colors for the schedules.
     * (this is kinda.. dumb)
     */
    private function random_html_color()
    {
        $letters = 'ABCDEF0123456789';
        $result = '';
        while (strlen($result) < 6) {
            $idx = rand(0, 16);
            $result = $result . substr($letters, $idx, 1);
        }
        return '#' . $result;
    }

    /*calendar.createSchedules([{
        id: '1',
        calendarId: '1',
        title: "Test",
        category: 'time',
        dueDateClass: '',
        start: '2019-07-03',
        end: '2019-07-10'
    }]);*/

    public function transformReservationCalendar(Reservation $res)
    {
        $array = [
            'id' => (int) $res->id,
            'title' => $res->name,
            'category' => 'time',
            'start' => explode(' ', $res->start . '')[0],
            'end' => explode(' ', $res->end . '')[0],
            'body' => $res->notes,
            'bgColor' => $this->random_html_color()
        ];
        return $array;
    }
}
