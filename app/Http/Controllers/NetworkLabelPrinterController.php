<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Services\NetworkLabelPrinter\PrinterResolver;
use App\Services\NetworkLabelPrinter\PrintServerClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends a label to an external network print-server (custom fork feature).
 *
 * Hybrid addition alongside the native PDF/DYMO label engine, which only
 * renders PDFs for the browser / print queue and does not cover this
 * connection model.
 *
 * The label payload is `<tag>|<name>|<subtitle>`, where the tag encodes the
 * item type (asset tag as-is, otherwise AC-/CM-/CS-/BX- plus the id).
 */
class NetworkLabelPrinterController extends Controller
{
    public function __construct(
        private PrinterResolver $resolver,
        private PrintServerClient $client,
    ) {
    }

    public function printAssetLabel(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('view', $asset);

        return $this->dispatchLabel(
            $request,
            $asset->location,
            (string) $asset->asset_tag,
            (string) $asset->name,
            (string) optional(optional($asset->model)->category)->name,
        );
    }

    public function printAccessoryLabel(Request $request, Accessory $accessory): RedirectResponse
    {
        $this->authorize('view', $accessory);

        return $this->dispatchLabel(
            $request,
            $accessory->location,
            'AC-'.$accessory->id,
            (string) $accessory->name,
            (string) optional($accessory->category)->name,
        );
    }

    public function printComponentLabel(Request $request, Component $component): RedirectResponse
    {
        $this->authorize('view', $component);

        return $this->dispatchLabel(
            $request,
            $component->location,
            'CM-'.$component->id,
            (string) $component->name,
            (string) optional($component->category)->name,
        );
    }

    public function printConsumableLabel(Request $request, Consumable $consumable): RedirectResponse
    {
        $this->authorize('view', $consumable);

        return $this->dispatchLabel(
            $request,
            $consumable->location,
            'CS-'.$consumable->id,
            (string) $consumable->name,
            (string) optional($consumable->category)->name,
        );
    }

    public function printLocationLabel(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('view', $location);

        // A location's own label is routed by the location itself, and carries
        // its parent's name as the subtitle.
        return $this->dispatchLabel(
            $request,
            $location,
            'BX-'.$location->id,
            (string) $location->name,
            (string) optional($location->parent)->name,
        );
    }

    /**
     * Resolve the printer for the item's location and send the label.
     */
    private function dispatchLabel(Request $request, ?Location $location, string $tag, string $name, string $subtitle): RedirectResponse
    {
        $printer = $this->resolver->resolve($location, $request->input('printer'));

        if (! $printer) {
            return redirect()->back()->with('error', trans('label-printer.no_printer'));
        }

        [$serverUrl, $printerName] = $printer;

        $httpCode = $this->client->send($serverUrl, $tag, $name, $subtitle);

        return match ($httpCode) {
            200 => redirect()->back()->with('success', trans('label-printer.queued', ['printer' => $printerName])),
            403 => redirect()->back()->with('error', trans('label-printer.denied', ['printer' => $printerName])),
            0 => redirect()->back()->with('error', trans('label-printer.unreachable', ['printer' => $printerName])),
            default => redirect()->back()->with('error', trans('label-printer.failed', ['printer' => $printerName, 'code' => $httpCode])),
        };
    }
}
