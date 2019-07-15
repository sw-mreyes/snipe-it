<!-- 
    Shows a list of reservation timeframes e.g.

    *Reservations*
    - [from] -> [to] by [name]
    - [from] -> [to] by [name]
    - [from] -> [to] by [name]
    ..

-->

@if(count($reservations) > 0)
<h4>Reservations</h4>

@foreach($reservations as $reservation)
<li>
    <p>
        <img src="{{ $reservation->user->present()->gravatar() }}" class="user-image-inline" alt="{{ $reservation->user->present()->fullName() }}">
        {!! $reservation->user->present()->glyph() . ' ' .$reservation->user->present()->nameUrl() !!}
        <br>
        <b>{{ \App\Helpers\Helper::getFormattedDateObject($reservation->start, 'date', false) }}</b>
        →
        <b> {{ \App\Helpers\Helper::getFormattedDateObject($reservation->end, 'date', false) }}</b>
        (<a href="{{ route('reservations.show', $reservation->id) }}">{{$reservation->name}}</a>)
    </p>
</li>
@endforeach

@endif