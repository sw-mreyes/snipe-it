<?php

namespace App\Services\NetworkLabelPrinter;

use App\Models\Location;

/**
 * Resolves which network print-server an item's label should be sent to
 * (custom fork feature).
 *
 * Security: the print-server host ALWAYS comes from server-side config. The
 * optional `printer` request parameter can only *select* a configured printer
 * by its key — it is never interpolated into a URL — so it cannot be used to
 * point printing at an arbitrary host (no SSRF surface).
 */
class PrinterResolver
{
    /**
     * Names of the configured printers, in config order.
     *
     * @return array<int, string>
     */
    public function printerNames(): array
    {
        return array_keys((array) config('sw-label-printer.printers', []));
    }

    /**
     * Resolve the print-server for an item.
     *
     * With no explicit printer, the item's *top-level* location decides: an
     * item in "Berlin > Floor 2 > Room 5" resolves through the mapping entry
     * for "Berlin". Items without any location use the '-1' entry. An unmapped
     * location resolves to nothing — deliberately, so a label never silently
     * comes out at another site's printer.
     *
     * @return array{0: string, 1: string}|null [base_url, printer_name], or null when nothing resolves
     */
    public function resolve(?Location $location, ?string $printerParam = null): ?array
    {
        $printers = (array) config('sw-label-printer.printers', []);
        $mapping = (array) config('sw-label-printer.location_mapping', []);

        // Explicit selection: only honored when it names a configured printer.
        if ($printerParam !== null && $printerParam !== '') {
            return array_key_exists($printerParam, $printers)
                ? [$printers[$printerParam], $printerParam]
                : null;
        }

        $locationId = $location ? (string) $this->rootLocationId($location) : '-1';
        $printerName = $mapping[$locationId] ?? null;

        if ($printerName === null || ! array_key_exists($printerName, $printers)) {
            return null;
        }

        return [$printers[$printerName], $printerName];
    }

    /**
     * Walk up the location tree to its root and return that id. Guards against
     * a cyclic parent chain.
     */
    private function rootLocationId(Location $location): int
    {
        $root = $location;
        $seen = [$location->id];

        while ($root->parent && ! in_array($root->parent->id, $seen, true)) {
            $root = $root->parent;
            $seen[] = $root->id;
        }

        return (int) $root->id;
    }
}
