<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Category;
use App\Models\Location;

use Input;
use App\Helpers\Helper;

/** This controller handles interaction with the label print server.
 *
 * @version    v1.0
 * @author [M. Reyes] [<mreyes@schutzwerk.com>]
 */
class LabelPrinterController extends Controller
{

    /**
     * Send a print request to the printer server.
     *
     * The request is a POST containing a b64 encoded string
     * of the tag and 2 lines of information, the name & category of
     * the object. The object type is encoded in the tag (e.g. SW, CM..)
     *
     * TODO:
     *  - Maybe get the tag prefixes via .env file to make it more configurable ?
     *  - Use CUPS ?
     */
    private function printLabel($tag, $name, $category, $server_addr)
    {
        $data_b64 = base64_encode($tag . '|' . $name . '|' . $category);
        // create curl resource
        $ch = curl_init();
        // set url
        $print_server = $server_addr . "/print?&data=" . $data_b64;
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

    private function get_printer_by_location_or_param($location)
    {
        // Parse the .env config
        $cfg = Helper::parse_printer_config();
        $server_list = $cfg[0];
        $location_mapping = $cfg[1];

        $arg = Input::get('printer');
        if ($arg) {
            if (!array_key_exists($arg, $server_list)) {
                return null;
            }
            return array($server_list[$arg], $arg);
        }

        // get the top-level parent id of the given location.
        // -1 if no location is set.
        if (!$location) {
            $location_id = -1;
        } else {
            $next = $location;
            $root = $location;
            while ($next = Location::find($next->parent_id) and $next->id != $root->id) {
                $root = $next;
            }
            $location_id = $root->id;
        }
        // Check if a mapping exists for the location_id.
        if (!array_key_exists($location_id, $location_mapping)) {
            return null;
        } else {
            $printer_location = $location_mapping[$location_id];
            if (!array_key_exists($printer_location, $server_list)) return null;
            return array($server_list[$printer_location], $printer_location);
        }
    }


    /**
     * Print an Accessory label.
     */
    public function printAccessoryLabel($accessoryID = null)
    {
        $accessory = Accessory::find($accessoryID);
        $category = Category::where('id', '=', $accessory->category_id)->first();

        if (!$accessory) {
            return redirect()->back()->with('error', 'Accessory not found!');
        }
        if (!$category) {
            return redirect()->back()->with('error', 'Category not found!');
        }
        // Determine which label printer to use
        if ($print_server = $this->get_printer_by_location_or_param($accessory->location)) {
            // Send the request
            $httpcode = $this->printLabel('AC-' . $accessoryID, $accessory->name, $category->name, $print_server[0]);
            if ($httpcode == 200) {
                return redirect()->back()->with('success', 'Print Job queued in ' . $print_server[1] . '!');
            }
            if ($httpcode == 403) {
                return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': Permission denied!');
            }
            return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': ' . $httpcode);
        } else {
            return redirect()->back()->with('error', 'Could not queue print job! (No printer available)');
        }
    }

    /**
     * Print an Asset label.
     */
    public function printAssetLabel($asset_id = null)
    {
        if (is_null($asset = Asset::find($asset_id))) {
            return redirect()->route('hardware.index')->with('error', trans('admin/hardware/message.not_found'));
        }

        $model = AssetModel::where('id', '=', $asset->model_id)->first();
        $category = Category::where('id', '=', $model->category_id)->first();

        if (!$model) {
            return redirect()->back()->with('error', 'Model not found!');
        }
        if (!$category) {
            return redirect()->back()->with('error', 'Category not found!');
        }

        // Determine which label printer to use
        if ($print_server = $this->get_printer_by_location_or_param($asset->location)) {
            $httpcode = $this->printLabel($asset->asset_tag, $asset->name, $category->name, $print_server[0]);
            if ($httpcode == 200) {
                return redirect()->back()->with('success', 'Print Job queued in ' . $print_server[1] . '!');
            }
            if ($httpcode == 403) {
                return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': Permission denied!');
            }
            return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': ' . $httpcode);
        } else {
            return redirect()->back()->with('error', 'Could not queue print job! (No printer available)');
        }


    }

    /**
     * Print a consumable label
     */
    public function printConsumableLabel($consumableID)
    {
        if (is_null($consumable = Consumable::find($consumableID))) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.not_found'));
        }
        if ($print_server = $this->get_printer_by_location_or_param($consumable->location)) {
            $httpcode = $this->printLabel('CS-' . $consumableID, $consumable->name, $consumable->category->name, $print_server[0]);
            if ($httpcode == 200) {
                return redirect()->route('consumables.index')->with('success', 'Print Job queued in ' . $print_server[1] . '!');
            }
            if ($httpcode == 403) {
                return redirect()->route('consumables.index')->with('error', 'Could not queue print job in ' . $print_server[1] . ': Permission denied!');
            }
            return redirect()->route('consumables.index')->with('error', 'Could not queue print job in ' . $print_server[1] . ': ' . $httpcode);
        } else {
            return redirect()->route('consumables.index')->with('error', 'Could not queue print job! (No printer available)');
        }


    }

    /**
     * Print a component label
     */
    public function printComponentLabel($componentID)
    {
        if (is_null($component = Component::find($componentID))) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.not_found'));
        }

        if ($print_server = $this->get_printer_by_location_or_param($component->location)) {
            $httpcode = $this->printLabel('CM-' . $componentID, $component->name, $component->category->name, $print_server[0]);
            if ($httpcode == 200) {
                return redirect()->back()->with('success', 'Print Job queued in ' . $print_server[1] . '!');
            }
            if ($httpcode == 403) {
                return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': Permission denied!');
            }
            return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': ' . $httpcode);
        } else {
            return redirect()->back()->with('error', 'Could not queue print job! (No printer available)');
        }
    }

    /**
     * Print a location label.
     */
    public function printLocationLabel($locationID)
    {
        if (is_null($location = Location::find($locationID))) {
            return redirect()->back()->with('error', trans('admin/locations/message.not_found'));
        }

        $parent_name = '';
        if ($location->parent) {
            $parent_name = $location->parent->name;
        }

        if ($print_server = $this->get_printer_by_location_or_param($location)) {
            $httpcode = $this->printLabel('BX-' . $locationID, $location->name, $parent_name, $print_server[0]);
            if ($httpcode == 200) {
                return redirect()->back()->with('success', 'Print Job queued in ' . $print_server[1] . '!');
            }
            if ($httpcode == 403) {
                return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': Permission denied!');
            }
            return redirect()->back()->with('error', 'Could not queue print job in ' . $print_server[1] . ': ' . $httpcode);
        } else {
            return redirect()->back()->with('error', 'Could not queue print job! (No printer available)');
        }
    }
}
