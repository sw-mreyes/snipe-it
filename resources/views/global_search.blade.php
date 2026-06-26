@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.search') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
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
            @else
                <table
                    data-cookie-id-table="globalSearchTable"
                    data-id-table="globalSearchTable"
                    data-side-pagination="server"
                    data-search="false"
                    id="globalSearchTable"
                    data-url="{{ route('api.search.index', ['search' => $query]) }}"
                    class="table table-striped snipe-table">
                    <thead>
                        <tr>
                            <th data-field="type" data-formatter="searchTypeFormatter">{{ trans('general.type') }}</th>
                            <th data-field="name" data-formatter="searchNameFormatter">{{ trans('general.name') }}</th>
                            <th data-field="identifier">{{ trans('general.asset_tag') }}</th>
                            <th data-field="category">{{ trans('general.category') }}</th>
                            <th data-field="location">{{ trans('general.location') }}</th>
                            <th data-field="assigned_to">{{ trans('admin/hardware/table.checkoutto') }}</th>
                            <th data-field="actions" data-formatter="searchActionsFormatter" class="text-right">{{ trans('table.actions') }}</th>
                        </tr>
                    </thead>
                </table>
            @endif
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop
