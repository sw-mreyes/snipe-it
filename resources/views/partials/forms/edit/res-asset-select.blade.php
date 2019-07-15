<!-- Asset -->
<div id="assigned_asset" class="form-group{{ $errors->has($fieldname) ? ' has-error' : '' }}" {!! (isset($style)) ? ' style="' .e($style).'"' : '' !!}>
    {{ Form::label($fieldname, $translated_name, array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7 required">
        <select class="js-data-ajax select2" data-endpoint="hardware" data-placeholder="{{ trans('general.select_asset') }}" name="{{ $fieldname }}" style="width: 100%" id="res-asset-selection" multiple>
            @if ($item)
                @foreach ($item->assets as $asset)
                <option value="{{ $asset->id }}" selected="selected">
                    {{$asset->present()->fullName}}
                </option>
                @endforeach
            @endif
            @if ($forAsset)
            <option value="{{ $forAsset->id }}" selected="selected">
                {{$forAsset->present()->fullName}}
            </option>
            @endif
        </select>
    </div>
    {!! $errors->first($fieldname, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fa fa-times"></i> :message</span></div>') !!}

</div>