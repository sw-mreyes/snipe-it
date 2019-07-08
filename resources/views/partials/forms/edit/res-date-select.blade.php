<!-- Purchase Date -->
<div class="form-group">
   <label for="res-date" class="col-md-3 control-label">{{ $translated_name }}</label>
   <div class="input-group col-md-3">
        <div class="input-group date" data-provide="datepicker" data-date-format="yyyy-mm-dd"  data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" 
            name="{{ $fieldname }}" 
            id="{{ $fieldname }}"
            value="{{ $item->$fieldname }}">
            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
       </div>
   </div>
</div>

