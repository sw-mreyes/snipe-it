@props([
    'required' => false,
    'item' => null,
])
{{-- Native date+time input. No JS dependency: the browser supplies the picker
     and renders it in the user's locale (24-hour under de-*). --}}
<input
    {{ $attributes->merge(['class' => 'form-control']) }}
    @required($required)
/>
