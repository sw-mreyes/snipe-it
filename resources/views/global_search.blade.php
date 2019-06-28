@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.global_search') }}
@parent
@stop


{{-- Page content --}}
@section('content')

<h4>{{$query}}</h4>

<div class="table-responsive">
    <table class="display table table-hover">
        <thead>
            <tr>
                <th class="col-md-1">{{ trans('general.type') }}</th>
                <th class="col-md-1">{{ trans('general.tag') }} / {{ trans('general.id') }}</th>
                <th class="col-md-2">{{ trans('general.name') }}</th>
                <th class="col-md-1">{{ trans('general.category') }}</th>
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
                    @elseif ($e->type == 'Location')
                    <a href="{{ route('locations.show', $e->id)}}">{{$e->tag}}</a>
                    @elseif ($e->type == 'Category')
                    <a href="{{ route('categories.show', $e->id)}}">{{$e->id}}</a>
                    @elseif ($e->type == 'Asset Model')
                    <a href="{{ route('models.show', $e->id)}}">{{$e->id}}</a>
                    @else
                    ERROR
                    @endif
                </td>
                <!-- Name -->                
                <td>
                    {{$e->name}}            
                </td>
                <!-- Category -->                
                <td>{{$e->category}}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


</table>
@stop