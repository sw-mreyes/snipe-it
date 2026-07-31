@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('global-search.results') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        {{-- The bordered box header carries the page title; a floated table_header
             would collide with the search form directly below it. --}}
        <x-box :header="$query !== '' ? trans('global-search.results_for', ['term' => $query]) : trans('global-search.results')">

            {{-- Restating the query here keeps it editable without going back to
                 the navbar, and shows what the table below is actually showing. --}}
            <form method="GET" action="{{ route('search') }}" role="search" style="margin-bottom: 15px;">
                <div class="input-group col-md-6 col-xs-12" style="padding-left: 0;">
                    <input type="text" name="search" class="form-control" value="{{ $query }}"
                           placeholder="{{ trans('global-search.placeholder') }}"
                           aria-label="{{ trans('global-search.placeholder') }}" autofocus>
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit">
                            <x-icon type="search" class="fa-fw" />
                            <span class="sr-only">{{ trans('general.search') }}</span>
                        </button>
                    </span>
                </div>
            </form>

            @if ($query === '')
                <p class="text-muted">{{ trans('global-search.help') }}</p>
            @else
                <x-table
                    name="globalSearch"
                    :presenter="\App\Presenters\SearchResultPresenter::dataTableLayout()"
                    :api_url="route('api.search.index', ['q' => $query])"
                    show_search="false"
                    show_column_search="false"
                    show_advanced_search="false"
                    fixed_right_number="1"
                    sort_field="type"
                    export_filename="search-{{ date('Y-m-d') }}"
                />
            @endif

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop
