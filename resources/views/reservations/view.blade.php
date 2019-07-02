@extends('layouts/default')

{{-- Page title --}}
@section('title')

{{ trans('general.reservation') }}:
{{ $reservation->name }}

@parent
@stop

@section('header_right')
<a href="{{ route('reservations.edit', ['reservation' => $reservation->id]) }}" class="btn btn-sm btn-primary pull-right">
    {{ trans('reservations.update') }} </a>
@stop

{{-- Page content --}}
@section('content')

<div class="box box-default">
    <div class="box-header with-border">
        <div class="box-heading">
            <h3 class="box-title">{{ trans('reservations.index') }}</h3>
        </div>
    </div>
    <div class="box-body">
        <div class="table table-responsive">
            <table 
            data-columns="{{ \App\Presenters\ReservationPresenter::assetTableLayout() }}" 
            data-cookie-id-table="assetsListingTable" 
            data-pagination="true" 
            data-id-table="assetsListingTable" 
            data-search="true" 
            data-side-pagination="server" 
            data-show-columns="true" 
            data-show-export="true" 
            data-show-refresh="true" 
            data-sort-order="asc" 
            id="assetsListingTable" 
            class="table table-striped snipe-table" 
            data-url="{{route('api.reservations.assets', ['reservation_id' => $reservation->id]) }}">
            <table>
        </div><!-- /.table-responsive -->
    </div><!-- /.box-body -->
</div>
<!--/.box-->

@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['search' => true])
@stop