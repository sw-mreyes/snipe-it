<?php

namespace App\Services\NetworkLabelPrinter;

use App\Models\Location;

/**
 * Resolves which network print-server an item's label should be sent to
 * (custom fork feature).
 *
 * Security: the print-server host is ALWAYS taken from server-side config. The
 * optional `printer` request parameter can only *select* a configured printer
 * by its key — it is never interpolated into a URL — so it cannot be used to
 * point printing at an arbitrary host (no SSRF surface).
 */
class PrinterResolver
{
    /**
     * @return array{0: string, 1: string}|null  [base_url, printer_name] or null when none resolves
     */
    public function resolve(?Location $location, ?string $printerParam = null): ?array
    {
        $printers = (array) config('sw-label-printer.printers', []);
        $mapping = (array) config('sw-label-printer.location_mapping', []);

        // Explicit printer selection: only honored if it names a configured printer.
        if ($printerParam !== null && $printerParam !== '') {
            if (array_key_exists($printerParam, $printers)) {
                return [$printers[$printerParam], $printerParam];
            }

            return null;
        }

        // Otherwise map by the item's top-level (root) location, falling back to '-1'.
        $locationId = $location ? (string) $this->rootLocationId($location) : '-1';

        $printerName = $mapping[$locationId] ?? ($mapping['-1'] ?? null);

        if ($printerName === null || ! array_key_exists($printerName, $printers)) {
            return null;
        }

        return [$printers[$printerName], $printerName];
    }

    /**
     * Walk up the location tree to its root and return that id.
     */
    private function rootLocationId(Location $location): int
    {
        $root = $location;
        $seen = [];

        while ($root->parent && ! in_array($root->id, $seen, true)) {
            $seen[] = $root->id;
            $root = $root->parent;
        }

        return (int) $root->id;
    }
}
