@extends('layouts/default')

{{-- Page title --}}
@section('title')

{{ trans('reservations.view') }}:
{{ $reservation->name }}

@parent
@stop

@section('header_right')
<a href="{{ route('reservations.delete', ['reservation' => $reservation->id]) }}" class="btn btn-sm btn-danger pull-right">{{ trans('reservations.delete') }} </a>
<a href="{{ route('reservations.edit', ['reservation' => $reservation->id]) }}" class="btn btn-sm btn-primary pull-right">{{ trans('reservations.update') }} </a>
@stop

{{-- Page content --}}
@section('content')

<div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
        <li class="active">
            <a href="#details" data-toggle="tab"><span class="hidden-lg hidden-md"><i class="fa fa-info-circle"></i></span> <span class="hidden-xs hidden-sm">{{ trans('general.details') }}</span></a>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade in active" id="details">
            <div class="row">
                <div class="col-md-8">
                    <div class="table-responsive" style="margin-top: 10px;">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td>{{ trans('general.user') }}</td>
                                    <td>
                                        <p><img src="{{ $reservation->user->present()->gravatar() }}" class="user-image-inline" alt="{{ $reservation->user->present()->fullName() }}"></p>
                                        <p>{{ $reservation->user->present()->fullName() }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ trans('general.notes') }}</td>
                                    <td>{{ $reservation->notes }}</td>
                                </tr>
                                <tr>
                                    <td>{{ trans('reservations.start') }}</td>
                                    <td>{{ $reservation->start }}</td>
                                </tr>
                                <tr>
                                    <td>{{ trans('reservations.end') }}</td>
                                    <td>{{ $reservation->end }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div><!-- /.table-responsive -->
                </div><!-- /.col-md-8 -->
            </div><!-- /.row -->
        </div><!-- /.tab-pane -->
    </div><!-- /.tab-content -->
</div><!-- /.nav-tabs-custom -->


<div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
        <li class="active">
            <a href="#assets" data-toggle="tab"><span class="hidden-lg hidden-md"><i class="fa fa-info-circle"></i></span> <span class="hidden-xs hidden-sm">{{ trans('general.assets') }}</span></a>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade in active" id="assets">
            <div class="box-body">
                <div class="table table-responsive">
                    <table data-columns="{{ \App\Presenters\ReservationPresenter::assetTableLayout() }}" data-cookie-id-table="assetsListingTable" data-pagination="true" data-id-table="assetsListingTable" data-search="false" data-side-pagination="server" data-show-columns="true" data-show-export="false" data-show-refresh="true" data-sort-order="asc" id="assetsListingTable" class="table table-striped snipe-table" data-url="{{route('api.reservations.assets', ['reservation_id' => $reservation->id]) }}">
                    </table>
                </div><!-- /.table-responsive -->
            </div><!-- /.box-body -->
        </div><!-- /.tab-pane -->
    </div><!-- /.tab-content -->
</div><!-- /.nav-tabs-custom -->
@stop
@section('moar_scripts')
@include ('partials.bootstrap-table', ['search' => true])
@stop