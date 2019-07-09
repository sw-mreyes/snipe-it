<div>
    @if($obj->company)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.company') }}: </b> </div>
        <div class='cold-md-4'> <a href="{{ route('companies.show', $obj->company->id) }}">{{ $obj->company->name }}</a> </div>
    </div>
    @endif
    @if($obj->category)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.category') }}: </b> </div>
        <div class='cold-md-2'> <a href="{{ route('categories.show', $obj->category->id) }}">{{ $obj->category->name }}</a> </div>
    </div>
    @endif
    @if($obj->supplier)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.supplier') }}: </b> </div>
        <div class='cold-md-4'> <a href="{{ route('suppliers.show', $obj->supplier->id) }}">{{ $obj->supplier->name }}</a> </div>
    </div>
    @endif
    @if($obj->manufacturer)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.manufacturer') }}: </b> </div>
        <div class='cold-md-4'> <a href="{{ route('manufacturers.show', $obj->manufacturer->id) }}">{{ $obj->manufacturer->name }}</a> </div>
    </div>
    @endif
    @if($obj->location)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.location') }}: </b> </div>
        <div class='cold-md-4'> <a href="{{ route('locations.show', $obj->location->id) }}">{{ $obj->location->name }} </a></div>
    </div>
    @endif
    @if($obj->model_number)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.model_no') }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->model_number }} </div>
    </div>
    @endif
    @if($obj->order_number)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.order_number') }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->order_number }} </div>
    </div>
    @endif
    @if($obj->serial)
    <div class='row'>
        <div class='col-md-4'> <b> {{  trans('admin/hardware/form.serial')  }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->serial }} </div>
    </div>
    @endif
    @if($obj->purchase_date)
    <div class='row'>
        <div class='col-md-4'> <b>{{ trans('general.purchase_date') }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->purchase_date }} </div>
    </div>
    @endif
    @if($obj->purchase_cost)
    <div class='row'>
        <div class='col-md-4'> <b>{{ trans('general.purchase_cost') }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->purchase_cost }} </div>
    </div>
    @endif
    @if($obj->qty)
    <div class='row'>
        <div class='col-md-4'> <b> {{ trans('general.qty') }}: </b> </div>
        <div class='cold-md-4'> {{ $obj->numRemaining() }} <b>/</b> {{ $obj->qty }} </div>
    </div>
    @endif
</div>