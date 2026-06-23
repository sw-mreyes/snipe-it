{{-- Reservations for this asset (custom fork feature). Expects $asset in scope. --}}
@php
    $assetReservations = \App\Models\Reservation::with('user')
        ->forAsset($asset->id)
        ->where('end', '>=', now())
        ->orderBy('start', 'asc')
        ->get();
@endphp

@if ($assetReservations->isNotEmpty())
    <div class="panel box box-default">
        <div class="box-header with-border">
            <h2 class="box-title"><x-icon type="calendar" /> {{ trans('reservations.reservations') }}</h2>
        </div>
        <div class="box-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{{ trans('reservations.name') }}</th>
                        <th>{{ trans('reservations.user') }}</th>
                        <th>{{ trans('reservations.start') }}</th>
                        <th>{{ trans('reservations.end') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assetReservations as $reservation)
                        <tr>
                            <td>
                                <a href="{{ route('reservations.show', ['reservation' => $reservation->id]) }}">{{ $reservation->name }}</a>
                            </td>
                            <td>
                                @if ($reservation->user)
                                    {{ $reservation->user->first_name }} {{ $reservation->user->last_name }}
                                @endif
                            </td>
                            <td>{{ $reservation->start?->format('Y-m-d H:i') }}</td>
                            <td>{{ $reservation->end?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
