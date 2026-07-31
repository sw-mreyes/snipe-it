@props([
    'item' => null,
    'route' => null,
    'wide' => false,
])

{{-- Network label printing (custom fork feature).

     Primary action prints on the printer mapped to the item's location. When
     more than one printer is configured, a split dropdown lets the user pick
     one explicitly. Renders nothing when no print server is configured. --}}

@php($printers = app(\App\Services\NetworkLabelPrinter\PrinterResolver::class)->printerNames())

@can('view', $item)
    @if (count($printers) > 0)
        <!-- start network label button component -->
        @if (count($printers) === 1)
            <a href="{{ $route }}"
               class="btn btn-sm btn-default hidden-print{{ $wide == 'true' ? ' btn-block btn-social' : '' }}"
               data-tooltip="true" data-placement="top" data-title="{{ trans('label-printer.print_label') }}">
                <x-icon type="print" class="fa-fw" />
                @if ($wide == 'true')
                    {{ trans('label-printer.print_label') }}
                @endif
            </a>
        @else
            <div class="btn-group hidden-print{{ $wide == 'true' ? ' btn-block' : '' }}">
                <a href="{{ $route }}"
                   class="btn btn-sm btn-default{{ $wide == 'true' ? ' btn-social' : '' }}"
                   @if ($wide == 'true') style="width: calc(100% - 32px);" @endif
                   data-tooltip="true" data-placement="top" data-title="{{ trans('label-printer.print_label') }}">
                    <x-icon type="print" class="fa-fw" />
                    @if ($wide == 'true')
                        {{ trans('label-printer.print_label') }}
                    @endif
                </a>
                <button type="button" class="btn btn-sm btn-default dropdown-toggle"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="caret"></span>
                    <span class="sr-only">{{ trans('label-printer.choose_printer') }}</span>
                </button>
                <ul class="dropdown-menu">
                    <li class="dropdown-header">{{ trans('label-printer.choose_printer') }}</li>
                    @foreach ($printers as $printer)
                        <li>
                            <a href="{{ $route }}?printer={{ urlencode($printer) }}">
                                <x-icon type="print" class="fa-fw" /> {{ $printer }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <!-- end network label button component -->
    @endif
@endcan
