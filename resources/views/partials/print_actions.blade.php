@foreach(\App\Helpers\Helper::get_printer_locations() as $printer)
    <li role="presentation">
        <a href="{{ route($route, $obj->id) }}?printer={{$printer}}">{{ trans('general.print_label') }} ({{$printer}})</a>
    </li>
@endforeach

