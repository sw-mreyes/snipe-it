<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Services\NetworkLabelPrinter\PrinterResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sends a label to an external network print-server (custom fork feature).
 *
 * Hybrid addition alongside the native PDF/DYMO label engine. The print-server
 * host always comes from config (see PrinterResolver); the optional ?printer
 * query parameter can only select a configured printer by key.
 */
class NetworkLabelPrinterController extends Controller
{
    public function __construct(private PrinterResolver $resolver)
    {
    }

    public function printAssetLabel(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('view', $asset);

        $category = optional(optional($asset->model)->category)->name ?? '';

        return $this->dispatchLabel($request, $asset->location, $asset->asset_tag, (string) $asset->name, $category);
    }

    public function printAccessoryLabel(Request $request, Accessory $accessory): RedirectResponse
    {
        $this->authorize('view', $accessory);

        return $this->dispatchLabel($request, $accessory->location, 'AC-'.$accessory->id, (string) $accessory->name, optional($accessory->category)->name ?? '');
    }

    public function printComponentLabel(Request $request, Component $component): RedirectResponse
    {
        $this->authorize('view', $component);

        return $this->dispatchLabel($request, $component->location, 'CM-'.$component->id, (string) $component->name, optional($component->category)->name ?? '');
    }

    public function printConsumableLabel(Request $request, Consumable $consumable): RedirectResponse
    {
        $this->authorize('view', $consumable);

        return $this->dispatchLabel($request, $consumable->location, 'CS-'.$consumable->id, (string) $consumable->name, optional($consumable->category)->name ?? '');
    }

    public function printLocationLabel(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('view', $location);

        $parentName = optional($location->parent)->name ?? '';

        return $this->dispatchLabel($request, $location, 'BX-'.$location->id, (string) $location->name, $parentName);
    }

    /**
     * Resolve the printer for the item's location and send the label.
     */
    private function dispatchLabel(Request $request, ?Location $location, string $tag, string $name, string $category): RedirectResponse
    {
        $printer = $this->resolver->resolve($location, $request->input('printer'));

        if (! $printer) {
            return redirect()->back()->with('error', trans('label-printer.no_printer'));
        }

        [$serverUrl, $printerName] = $printer;

        $httpCode = $this->sendToPrintServer($serverUrl, $tag, $name, $category);

        if ($httpCode === 200) {
            return redirect()->back()->with('success', trans('label-printer.queued', ['printer' => $printerName]));
        }

        if ($httpCode === 403) {
            return redirect()->back()->with('error', trans('label-printer.denied', ['printer' => $printerName]));
        }

        return redirect()->back()->with('error', trans('label-printer.failed', ['printer' => $printerName, 'code' => $httpCode]));
    }

    /**
     * POST the (base64-encoded) label payload to the print-server and return
     * the HTTP status code (0 on transport failure).
     */
    private function sendToPrintServer(string $serverUrl, string $tag, string $name, string $category): int
    {
        $payload = base64_encode($tag.'|'.$name.'|'.$category);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($serverUrl, '/').'/print?data='.$payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno = curl_errno($ch)) {
            Log::warning('Network label print failed: '.curl_error($ch));
        }

        curl_close($ch);

        return $httpCode;
    }
}
