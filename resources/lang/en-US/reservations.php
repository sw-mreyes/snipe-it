<?php

return [
    'reservations' => 'Reservations',
    'reservation' => 'Reservation',
    'calendar' => 'Calendar',
    'list' => 'List',
    'create' => 'Create Reservation',
    'update' => 'Update Reservation',
    'name' => 'Name',
    'user' => 'Reserved for',
    'assets' => 'Assets',
    'start' => 'Start',
    'end' => 'End',
    'from' => 'From',
    'to' => 'To',
    'notes' => 'Notes',
    'none' => 'There are no reservations.',
    'placed' => 'Reservation placed successfully.',
    'updated' => 'Reservation updated successfully.',
    'deleted' => 'Reservation deleted successfully.',
    'deletion_failed' => 'Could not delete the reservation.',
    'reservation_not_found' => 'Reservation not found.',
    'invalid_timeframe' => 'The selected timeframe is invalid or overlaps an existing reservation for one of the chosen assets.',
    'checkout_warning' => 'Heads up: this asset has an active or upcoming reservation.',

    'mail' => [
        'placed_subject' => 'Reservation placed: :name',
        'placed_greeting' => 'A reservation has been placed.',
        'expected_checkin_subject' => 'A reservation expects asset :tag',
        'expected_checkin_greeting' => 'An asset you are currently responsible for has been reserved.',
    ],
];
