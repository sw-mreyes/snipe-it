<!-- Snippet (required for all tui elements) -->
<script src="{{ url(asset('js/tui-code-snippet.js')) }}" nonce="{{ csrf_token() }}"></script>

<!-- Calendar -->
@if($calendar)
<script src="{{ url(asset('js/tui-calendar.js')) }}" nonce="{{ csrf_token() }}"></script>
<link rel="stylesheet" href="{{ url(asset('css/tui-calendar.css')) }}">
@endif

<!-- Date Picker -->
@if($datepicker)
<script src="{{ url(asset('js/jquery-3.4.1.min.js')) }}" nonce="{{ csrf_token() }}"></script>
<script src="{{ url(asset('js/tui-time-picker.js')) }}" nonce="{{ csrf_token() }}"></script>
<script src="{{ url(asset('js/tui-date-picker.js')) }}" nonce="{{ csrf_token() }}"></script>
<link rel="stylesheet" href="{{ url(asset('css/tui-date-picker.css')) }}">
<link rel="stylesheet" href="{{ url(asset('css/tui-time-picker.css')) }}">
@endif