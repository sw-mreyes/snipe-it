<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Models\Accessory;
use App\Http\Transformers\AccessoriesTransformer;
use App\Models\Company;
/*== Patched ==*/
use App\Models\User;
use DB;
use Auth;
/*==*/


class AccessoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view', Accessory::class);
        $allowed_columns = ['id','name','model_number','eol','notes','created_at','min_amt','company_id'];

        $accessories = Accessory::with('category', 'company', 'manufacturer', 'users', 'location');

        if ($request->has('search')) {
            $accessories = $accessories->TextSearch($request->input('search'));
        }

        if ($request->has('company_id')) {
            $accessories->where('company_id','=',$request->input('company_id'));
        }

        if ($request->has('category_id')) {
            $accessories->where('category_id','=',$request->input('category_id'));
        }

        if ($request->has('manufacturer_id')) {
            $accessories->where('manufacturer_id','=',$request->input('manufacturer_id'));
        }

        if ($request->has('supplier_id')) {
            $accessories->where('supplier_id','=',$request->input('supplier_id'));
        }

        $offset = (($accessories) && (request('offset') > $accessories->count())) ? 0 : request('offset', 0);
        $limit = $request->input('limit', 50);
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        switch ($sort) {
            case 'category':
                $accessories = $accessories->OrderCategory($order);
                break;
            case 'company':
                $accessories = $accessories->OrderCompany($order);
                break;
            default:
                $accessories = $accessories->orderBy($sort, $order);
                break;
        }

        $accessories->orderBy($sort, $order);

        $total = $accessories->count();
        $accessories = $accessories->skip($offset)->take($limit)->get();
        return (new AccessoriesTransformer)->transformAccessories($accessories, $total);
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
        $this->authorize('create', Accessory::class);
        $accessory = new Accessory;
        $accessory->fill($request->all());

        if ($accessory->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $accessory, trans('admin/accessories/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $accessory->getErrors()));

    }

    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Accessory::class);
        $accessory = Accessory::findOrFail($id);
        return (new AccessoriesTransformer)->transformAccessory($accessory);
    }


    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function accessory_detail($id)
    {
        $this->authorize('view', Accessory::class);
        $accessory = Accessory::findOrFail($id);
        return (new AccessoriesTransformer)->transformAccessory($accessory);
    }


    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function checkedout($id)
    {
        $this->authorize('view', Accessory::class);

        $accessory = Accessory::findOrFail($id);
        if (!Company::isCurrentUserHasAccess($accessory)) {
            return ['total' => 0, 'rows' => []];
        }
        $accessory_users = $accessory->users;
        $total = $accessory_users->count();

        return (new AccessoriesTransformer)->transformCheckedoutAccessory($accessory_users, $total);
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
        $this->authorize('edit', Accessory::class);
        $accessory = Accessory::findOrFail($id);
        $accessory->fill($request->all());

        if ($accessory->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $accessory, trans('admin/accessories/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $accessory->getErrors()));
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
        $this->authorize('delete', Accessory::class);
        $accessory = Accessory::findOrFail($id);
        $this->authorize($accessory);

        if ($accessory->hasUsers() > 0) {
            return response()->json(Helper::formatStandardApiResponse('error', null,  trans('admin/accessories/message.assoc_users', array('count'=> $accessory->hasUsers()))));
        }

        $accessory->delete();
        return response()->json(Helper::formatStandardApiResponse('success', null,  trans('admin/accessories/message.delete.success')));

    }

    /* PATCH: Checkin/Checkout API */
      /**
     * Checkin an Accessory
     *
     * @author [M. Reyes] [<mreyes@schutzwerk.com>]
     * @param int $accessoryID
     * @since [v5.0]
     * @return JsonResponse
     */
    public function checkin(Request $request, $accessoryID){
        $this->authorize('checkin', Accessory::class);
        $user_id = $request->input('user_id');
        $response_payload = ['accessory'=> e($accessoryID), 'user'=>e($user_id)];
        // check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryID))){
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/accessories/message.checkin.accessory_does_not_exist')));
        }
        // check if the user exists
        if (is_null(User::find($request->input('user_id')))) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/accessories/message.checkin.user_does_not_exist')));
        }
        // Check if the accessory_user entry exists        
        if (is_null($accessory_user = DB::table('accessories_users')->where('assigned_to', $user_id)->first())) {
            return response()->json(Helper::formatStandardApiResponse('error', $response_payload, trans('admin/accessories/message.checkin.not_checkedout')));
        }
        $this->authorize('checkin', $accessory);        
        // Delete the entry. if the table changed
        if (DB::table('accessories_users')->where('id', '=', $accessory_user->id)->delete()) {
            // We succeeded
            event(new CheckoutableCheckedIn($accessory, User::find($user_id), Auth::user(), $request->input('note'), date('Y-m-d H:i:s')));
            return response()->json(Helper::formatStandardApiResponse('success', ['accessory'=> e($accessory->id)], trans('admin/accessories/message.checkin.success')));
        }        
        // else we failed
        return response()->json(Helper::formatStandardApiResponse('success',  ['accessory'=> e($accessory->id)], trans('admin/accessories/message.checkin.error')));
    }

     /**
     * Checkout an Accessory
     *
     * @author [M. Reyes] [<mreyes@schutzwerk.com>]
     * @param int $accessoryID
     * @since [v5.0]
     * @return JsonResponse
     */
    public function checkout(Request $request, $accessoryID){
        $this->authorize('checkout', Accessory::class);
        $user_id = $request->input('user_id');
        $response_payload = ['accessory'=> e($accessoryID), 'user'=>e($user_id)];
        
        // check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryID))){
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/accessories/message.checkout.accessory_does_not_exist')));
        }
        // check if the user exists
        if (!$user = User::find($user_id)) {
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/accessories/message.checkout.user_does_not_exist')));
        }        
        // make sure there is at least one accessory left for us to chekout.
        if(DB::table('accessories_users')->where('accessory_id', '=', $accessoryID)->count() >= $accessory->qty ){
            return response()->json(Helper::formatStandardApiResponse('success',  $response_payload,  trans('admin/accessories/message.checkout.error')));
        }
        $this->authorize('checkout', $accessory);
        // Update the accessory data
        $accessory->assigned_to = e($user_id);
        $accessory->users()->attach($accessory->id, [
            'accessory_id' => $accessory->id,
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => Auth::id(),
            'assigned_to' => $user_id
        ]);
        DB::table('accessories_users')->where('assigned_to', '=', $accessory->assigned_to)->where('accessory_id', '=', $accessory->id)->first();
        event(new CheckoutableCheckedOut($accessory, $user, Auth::user(), $request->input('note'), date('Y-m-d H:i:s')));
        return response()->json(Helper::formatStandardApiResponse('success',  $response_payload, trans('admin/accessories/message.checkout.success')));
    }
}
