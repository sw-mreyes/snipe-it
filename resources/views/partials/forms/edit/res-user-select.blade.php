<div id="assigned_user" class="form-group{{ $errors->has($fieldname) ? ' has-error' : '' }}" {!! (isset($style)) ? ' style="' .e($style).'"' : '' !!}>

    {{ Form::label($fieldname, $translated_name, array('class' => 'col-md-3 control-label')) }}

    <div class="col-md-7{{  ((isset($required)) && ($required=='true')) ? ' required' : '' }}">
        <select class="js-data-ajax" data-endpoint="users" data-placeholder="{{ trans('general.select_user') }}" name="{{ $fieldname }}" style="width: 100%" id="assigned_user_select">
            @if ($user_id = request($fieldname, (isset($item)) ? $item->{$fieldname} : ''))
            <option value="{{ $item->user->id }}" selected="selected">
                {{ $item->user->present()->fullName }}
            </option>
            @else
            <option value="{{Auth::user()->id}}" selected="selected">
                {{ Auth::user()->present()->fullName }}
            </option>
            @endif
        </select>
    </div>

    {!! $errors->first($fieldname, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fa fa-times"></i> :message</span></div>') !!}

</div>