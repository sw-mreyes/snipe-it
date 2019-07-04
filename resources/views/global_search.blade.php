@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.global_search') }}
@parent
@stop


{{-- Page content --}}
@section('content')

<h4>{{$query}}</h4>
<h5>{{count($search_result)}} hits</h5>

<div class="table-responsive">
    <table class="table table-striped snipe-table">
        <thead>
            <tr>
                <th class="col-md-1">{{ trans('general.type') }}</th>
                <th class="col-md-1">{{ trans('general.tag') }}</th>
                <th class="col-md-3">{{ trans('general.name') }} / {{ trans('general.asset_model') }}</th>
                <th class="col-md-2">{{ trans('general.category') }}</th>
                <th class="col-md-2">{{ trans('general.location') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($search_result as $e)
            <tr>
                <!-- Type -->
                <td>{{$e->type}}</td>
                <!-- Tag / ID -->
                <td>
                    @if ($e->type == 'Asset')
                    <a href="{{ route('hardware.view', $e->id)}}">{{$e->tag}}</a>
                    @elseif ($e->type == 'Accessory')
                    <a href="{{ route('accessories.show', $e->id)}}">{{$e->tag}}</a>
                    @elseif ($e->type == 'Component')
                    <a href="{{ route('components.show', $e->id)}}">{{$e->tag}}</a>
                    @elseif ($e->type == 'Consumable')
                    <a href="{{ route('consumables.show', $e->id)}}">{{$e->tag}}</a>
                    @else
                    ERROR
                    @endif
                </td>
                <!-- Name -->
                <td>
                    @if ($e->type == 'Asset')
                    @if ($e->name)
                    <!--{{ trans('general.name') }}-->
                    <a href="{{ route('hardware.view', $e->id)}}">{{$e->name}}</a> / <br>
                    @endif
                    <!--{{ trans('general.asset_model') }}-->
                    <a href="{{ route('models.show', $e->model->id)}}">{{$e->model->name}}</a>
                    @elseif ($e->type == 'Accessory')
                    <a href="{{ route('accessories.show', $e->id)}}">{{$e->name}}</a>
                    @elseif ($e->type == 'Component')
                    <a href="{{ route('components.show', $e->id)}}">{{$e->name}}</a>
                    @elseif ($e->type == 'Consumable')
                    <a href="{{ route('consumables.show', $e->id)}}">{{$e->name}}</a>
                    @else
                    ERROR
                    @endif
                </td>
                <!-- Category -->
                <td>
                    @if($e->category)
                    <a href="{{ route('categories.show', $e->category->id)}}">{{$e->category->name}}</a>
                    @endif
                </td>
                <!-- Location -->
                <td>
                    @if($e->location)
                    <a href="{{ route('locations.show', $e->location->id)}}">{{$e->location->name}}</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


</table>
@stop