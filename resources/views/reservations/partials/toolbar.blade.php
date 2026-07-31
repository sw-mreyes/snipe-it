{{-- Shared action bar for the reservation list and calendar (custom fork
     feature). Pass $active as 'list' or 'calendar' to mark the current view. --}}
@php($active = $active ?? '')

<div class="row" style="margin-bottom: 15px;">
    <div class="col-md-12">
        <div class="btn-group" role="group" aria-label="{{ trans('reservations.reservations') }}">
            <a href="{{ route('reservations.index') }}"
               class="btn btn-default{{ $active === 'list' ? ' active' : '' }}">
                <x-icon type="list" class="fa-fw" /> {{ trans('reservations.list') }}
            </a>
            <a href="{{ route('reservations.calendar') }}"
               class="btn btn-default{{ $active === 'calendar' ? ' active' : '' }}">
                <x-icon type="calendar" class="fa-fw" /> {{ trans('reservations.calendar') }}
            </a>
        </div>

        @can('checkout', \App\Models\Asset::class)
            <a href="{{ route('reservations.create') }}" class="btn btn-primary pull-right">
                <x-icon type="plus" class="fa-fw" /> {{ trans('reservations.create') }}
            </a>
        @endcan
    </div>
</div>
