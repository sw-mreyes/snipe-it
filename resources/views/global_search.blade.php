@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.search') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    @php
        $urlFor = function ($type, $model) {
            return match ($type) {
                'asset' => route('hardware.show', $model->id),
                'accessory' => route('accessories.show', $model->id),
                'component' => route('components.show', $model->id),
                'consumable' => route('consumables.show', $model->id),
                default => '#',
            };
        };
    @endphp

    <x-container>
        <x-box>
            <form method="GET" action="{{ route('search') }}" class="form-inline" style="margin-bottom: 15px;">
                <div class="input-group" style="width: 100%; max-width: 480px;">
                    <input type="text" name="search" class="form-control" value="{{ $query }}"
                           placeholder="{{ trans('general.search') }}" aria-label="{{ trans('general.search') }}">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit"><x-icon type="search" /></button>
                    </span>
                </div>
            </form>

            @if ($query === '')
                <p class="text-muted">{{ trans('general.search') }}…</p>
            @elseif ($results->isEmpty())
                <p class="text-muted">{{ trans('general.no_results') }}</p>
            @else
                <p class="text-muted">{{ $results->count() }}</p>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ trans('general.type') }}</th>
                                <th>{{ trans('general.name') }}</th>
                                <th>{{ trans('general.asset_tag') }}</th>
                                <th>{{ trans('general.category') }}</th>
                                <th>{{ trans('general.location') }}</th>
                                <th>{{ trans('admin/hardware/table.checkoutto') }}</th>
                                <th class="text-right">{{ trans('table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                                @php $type = $result['type']; $model = $result['model']; @endphp
                                <tr>
                                    <td>{{ ucfirst($type) }}</td>
                                    <td>
                                        <a href="{{ $urlFor($type, $model) }}">
                                            @if ($type === 'asset')
                                                {{ $model->present()->fullName ?: $model->asset_tag }}
                                            @else
                                                {{ $model->name }}
                                            @endif
                                        </a>
                                    </td>
                                    <td>{{ $type === 'asset' ? $model->asset_tag : '' }}</td>
                                    <td>{{ ($type !== 'asset' && $model->category) ? $model->category->name : '' }}</td>
                                    <td>{{ $model->location?->name }}</td>
                                    <td>
                                        @if ($type === 'asset' && $model->assignedTo)
                                            @if ($model->assigned_type === \App\Models\User::class)
                                                {{ $model->assignedTo->present()->fullName }}
                                            @else
                                                {{ $model->assignedTo->name }}
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($type === 'asset')
                                            <a href="{{ route('network-label.asset', $model->id) }}" class="btn btn-sm btn-default" title="{{ trans('label-printer.print_label') }}">
                                                <x-icon type="print" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-box>
    </x-container>
@stop
