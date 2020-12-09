<!-- Name that does not check if its required (?) -->
<div class="form-group {{ $errors->has('name') ? ' has-error' : '' }}">
    <label for="name" class="col-md-3 control-label">{{ $translated_name }}</label>
    <div class="col-md-7 col-sm-12">
        <input class="form-control" type="text" name="name" id="name" value="{{ request('name', $item->name) }}" />
        {!! $errors->first('name', '<span class="alert-msg"><i class="fa fa-times"></i> :message</span>') !!}
    </div>
</div>