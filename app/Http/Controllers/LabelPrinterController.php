<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Category;
use App\Models\Location;


/** This controller handles interaction with the label print server.
 *
 * @version    v1.0
 * @author [M. Reyes] [<mreyes@schutzwerk.com>]
 */
class LabelPrinterController extends Controller
{

    private function printLabel($tag, $name, $category)
    {
        $data_b64 =  base64_encode($tag . '|' . $name . '|' . $category);
        // create curl resource
        $ch = curl_init();
        // set url
        $print_server = env('PRINT_SERVER', "127.0.0.1:1130") . "/print?&data=" . $data_b64;
        curl_setopt($ch, CURLOPT_URL, $print_server);
        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // Use POST
        curl_setopt($ch, CURLOPT_POST, 1);
        // get status
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // close curl resource to free up system resources
        curl_close($ch);
        return $httpcode;
    }


    /**
     * Print a label.
     */
    public function printAccessoryLabel($accessoryID = null)
    {
        $accessory = Accessory::find($accessoryID);
        $category = Category::where('id', '=', $accessory->category_id)->first();
        // Send the request
        $httpcode = $this->printLabel('AC-' . $accessoryID, $accessory->name, $category->name);
        if ($httpcode == 200) {
            return redirect()->route('accessories.show', $accessoryID)->with('success', 'Print Job queued!');
        }
        if ($httpcode == 403) {
            return redirect()->route('accessories.show', $accessoryID)->with('error', 'Could not queue print job: Permission denied!');
        }
        return redirect()->route('accessories.show', $accessoryID)->with('error', 'Could not queue print job! (' . $httpcode . ')');
    }

    /**
     * Print a label.
     */
    public function printAssetLabel($asset_id = null)
    {
        if (is_null($asset = Asset::find($asset_id))) {
            return redirect()->route('hardware.index')->with('error', trans('admin/hardware/message.not_found'));
        }

        $model = AssetModel::where('id', '=', $asset->model_id)->first();
        $category = Category::where('id', '=', $model->category_id)->first();

        $httpcode = $this->printLabel($asset->asset_tag, $asset->name, $category->name);

        if ($httpcode == 200) {
            return redirect()->route('hardware.view', $asset_id)->with('success', 'Print Job queued!');
        }
        if ($httpcode == 403) {
            return redirect()->route('hardware.view', $asset_id)->with('error', 'Could not queue print job: Permission denied!');
        }
        return redirect()->route('hardware.view', $asset_id)->with('error', 'Could not queue print job! (' . $httpcode . ')');
    }

    public function printConsumableLabel($consumableID)
    {
        if (is_null($consumable = Consumable::find($consumableID))) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.not_found'));
        }

        $httpcode = $this->printLabel('CS-' . $consumableID, $consumable->name, $consumable->category->name);
        //
        if ($httpcode == 200) {
            return redirect()->route('consumables.index')->with('success', 'Print Job queued!');
        }
        if ($httpcode == 403) {
            return redirect()->route('consumables.index')->with('error', 'Could not queue print job: Permission denied!');
        }
        return redirect()->route('consumables.index')->with('error', 'Could not queue print job! (' . $httpcode . ')');
        //
    }

    public function printComponentLabel($componentID)
    {
        if (is_null($component = Component::find($componentID))) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.not_found'));
        }

        $httpcode = $this->printLabel('CM-' . $componentID, $component->name, $component->category->name);

        if ($httpcode == 200) {
            return redirect()->back()->with('success', 'Print Job queued!');
        }
        if ($httpcode == 403) {
            return redirect()->back()->with('error', 'Could not queue print job: Permission denied!');
        }
        return redirect()->back()->with('error', 'Could not queue print job! (' . $httpcode . ')');
    }

    public function printLocationLabel($locationID)
    {
        if (is_null($location = Location::find($locationID))) {
            return redirect()->route('locations.index')->with('error', trans('admin/locations/message.not_found'));
        }

        $parent_name = '';
        if ($location->parent) {
            $parent_name = $location->parent->name;
        }

        $httpcode = $this->printLabel('BX-' . $locationID, $location->name, $parent_name);

        if ($httpcode == 200) {
            return redirect()->route('locations.show', $locationID)->with('success', 'Print Job queued!');
        }
        if ($httpcode == 403) {
            return redirect()->route('locations.show', $locationID)->with('error', 'Could not queue print job: Permission denied!');
        }
        return redirect()->route('locations.show', $locationID)->with('error', 'Could not queue print job! (' . $httpcode . ')');
    }
}
