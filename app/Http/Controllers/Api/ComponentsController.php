<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Transformers\AssetsTransformer;
use App\Http\Transformers\ComponentsTransformer;
use App\Http\Transformers\ComponentsAssetsTransformer;
use App\Models\Component;
use App\Models\Company;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Helpers\Helper;
use Validator;
use Auth;
use Input;
use DB;


class ComponentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view', Component::class);
        $components = Company::scopeCompanyables(Component::select('components.*')
            ->with('company', 'location', 'category'));

        if ($request->filled('search')) {
            $components = $components->TextSearch($request->input('search'));
        }

        if ($request->filled('company_id')) {
            $components->where('company_id','=',$request->input('company_id'));
        }

        if ($request->filled('category_id')) {
            $components->where('category_id','=',$request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $components->where('location_id','=',$request->input('location_id'));
        }

        $offset = (($components) && (request('offset') > $components->count())) ? 0 : request('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $allowed_columns = ['id','name','min_amt','order_number','serial','purchase_date','purchase_cost','company','category','qty','location','image'];
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        switch ($sort) {
            case 'category':
                $components = $components->OrderCategory($order);
                break;
            case 'location':
                $components = $components->OrderLocation($order);
                break;
            case 'company':
                $components = $components->OrderCompany($order);
                break;
            default:
                $components = $components->orderBy($sort, $order);
                break;
        }

        $total = $components->count();
        $components = $components->skip($offset)->take($limit)->get();
        return (new ComponentsTransformer)->transformComponents($components, $total);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Component::class);
        $component = new Component;
        $component->fill($request->all());

        if ($component->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $component, trans('admin/components/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $component->getErrors()));
    }

    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Component::class);
        $component = Component::findOrFail($id);

        if ($component) {
            return (new ComponentsTransformer)->transformComponent($component);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', Component::class);
        $component = Component::findOrFail($id);
        $component->fill($request->all());

        if ($component->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $component, trans('admin/components/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $component->getErrors()));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('delete', Component::class);
        $component = Component::findOrFail($id);
        $this->authorize('delete', $component);
        $component->delete();
        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/components/message.delete.success')));
    }

    /**
     * Display all assets attached to a component
     *
     * @author [A. Bergamasco] [@vjandrea]
     * @since [v4.0]
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
    */
    public function getAssets(Request $request, $id)
    {
        $this->authorize('view', \App\Models\Asset::class);
        
        $component = Component::findOrFail($id);
        $assets = $component->assets();

        $offset = request('offset', 0);
        $limit = $request->input('limit', 50);
        $total = $assets->count();
        $assets = $assets->skip($offset)->take($limit)->get();
        return (new ComponentsTransformer)->transformCheckedoutComponents($assets, $total);
    }

    public function checkout(Request $request, $componentId)
    {
        $admin_user = Auth::user();
        $asset_id = e(Input::get('asset_id'));
        $response_payload = ['component'=> e($componentId), 'asset' => e($asset_id)];

        // Check if the component exists
        if (is_null($component = Component::find($componentId))) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkout.component_does_not_exist')));            
        }
        // authorize
        $this->authorize('checkout', $component);

        // make sure there are enough components left to checkout
        $max_to_checkout = $component->numRemaining();
        $validator = Validator::make($request->all(), [
            "asset_id"          => "required",
            "assigned_qty"      => "required|numeric|between:1,$max_to_checkout"
        ]);
        if ($validator->fails()) {
            // trans('admin/components/message.checkout.error')
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload, 'validator failed'));
        }
        
        // Check if the asset exists
        if (is_null($asset = Asset::find($asset_id))) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkout.asset_does_not_exist')));            
        }

        // Update the component data
        $component->asset_id = $asset_id;
        $component->assets()->attach($component->id, [
            'component_id' => $component->id,
            'user_id' => $admin_user->id,
            'created_at' => date('Y-m-d H:i:s'),
            'assigned_qty' => Input::get('assigned_qty'),
            'asset_id' => $asset_id
        ]);
        $component->logCheckout(e(Input::get('note')), $asset);
        return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkout.success')));            
        
    }

    public function checkin(Request $request, $componentId)
    {
        $response_payload = ['component'=> e($componentId)];
        $asset_id = $request->input('asset_id');
        
        // Check if the component exists
        if (is_null($component = Component::find($componentId))) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkin.component_does_not_exist')));            
        }

        // Check if the asset exists
        if (is_null($asset = Asset::find($asset_id))) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkin.asset_does_not_exist')));            
        }

        // Check if the component was checked out to the given asset        
        if (is_null($component_assets = DB::table('components_assets')->where([['component_id', $componentId], ['asset_id', $asset_id]])->first())) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkin.component_does_not_exist')));            
        }

      
        // authorize
        $this->authorize('checkin', $component);
        
        // make sure we dont checkin more then previously checked out.
        $max_to_checkin = $component_assets->assigned_qty;
        $validator = Validator::make($request->all(), [
            "checkin_qty" => "required|numeric|between:1,$max_to_checkin"
        ]);
        if ($validator->fails()) {
            //return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkin.error')));
            return response()->json(Helper::formatStandardApiResponse('success',  ['max'=>e($max_to_checkin)],  'validator failed'));
        }
        
        // Validation passed, so let's figure out what we have to do here.
        $qty_remaining_in_checkout = ($component_assets->assigned_qty - (int)$request->input('checkin_qty'));
        // We have to modify the record to reflect the new qty that's
        // actually checked out.
        $component_assets->assigned_qty = $qty_remaining_in_checkout;
        DB::table('components_assets')->where('id', $component_assets->id)->update(['assigned_qty' => $qty_remaining_in_checkout]);
        // Log the checkin
        $log = new Actionlog();
        $log->user_id = Auth::user()->id;
        $log->action_type = 'checkin from';
        $log->target_type = Asset::class;
        $log->target_id = $component_assets->asset_id;
        $log->item_id = $component_assets->component_id;
        $log->item_type = Component::class;
        $log->note = $request->input('note');
        $log->save();
        // If the checked-in qty is exactly the same as the assigned_qty,
        // we can simply delete the associated components_assets record
        if ($qty_remaining_in_checkout == 0) {
            DB::table('components_assets')->where('id', '=', $component_assets->id)->delete();
        }
        
        return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/components/message.checkin.success')));

    }
}
