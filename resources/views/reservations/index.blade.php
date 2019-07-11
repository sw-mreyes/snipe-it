@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('reservations.index') }}
@parent
@stop

@section('header_right')
<a href="{{ route('reservations.create') }}" class="btn btn-primary pull-right"></i> {{ trans('general.create') }}</a>
<a href="{{ route('reservations.calendar') }}" class="btn btn-primary pull-right"></i> {{ trans('reservations.calendar') }}</a>
@stop


{{-- Page content --}}
@section('content')


<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table 
                            data-columns="{{ \App\Presenters\ReservationPresenter::dataTableLayout() }}" 
                            data-cookie-id-table="reservationsTable" 
                            data-pagination="false" 
                            data-click-to-select="true"
                            data-id-table="reservationsTable" 
                            data-search="true" 
                            data-show-footer="true" 
                            data-side-pagination="server" 
                            data-show-columns="true" 
                            data-show-refresh="true" 
                            data-show-footer="true"
                            data-sort-order="asc" 
                            id="reservationsTable" 
                            class="table table-striped snipe-table" 
                            data-url="{{ route('api.reservations.index') }}">
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->
    </div>
</div>

@stop

@section('moar_scripts')
@include ('partials.bootstrap-table')
@stop