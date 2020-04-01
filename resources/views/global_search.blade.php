@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.global_search') }}
    @parent
@stop


{{-- Page content --}}
@section('content')

    <h4>{{$query}}</h4>

    <table
            data-advanced-search="false"
            data-click-to-select="true"
            data-columns="{{ \App\Presenters\SearchResultPresenter::dataTableLayout() }}"
            data-cookie-id-table="globalSearchTable"
            data-pagination="true"
            data-id-table="globalSearchTable"
            data-search="false"
            data-side-pagination="server"
            data-show-columns="true"
            data-show-export="false"
            data-show-footer="true"
            data-show-refresh="true"
            data-sort-order="asc"
            data-sort-name="name"
            data-toolbar="#toolbar"
            id="globalSearchTable"
            class="table table-striped snipe-table"
            data-url="{{ route('api.search.global') }}?search={{$query}}"
    >
    </table>

@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
    <!-- Reset pageNumber -->
    <script>
        document.cookie = "search/global.bs.table.pageNumber=1"
    </script>
@stop

