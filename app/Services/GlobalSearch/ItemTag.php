<?php

namespace App\Services\GlobalSearch;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\Setting;

/**
 * The human-readable tag printed on a physical label (custom fork feature),
 * and the reverse lookup for it.
 *
 * Assets carry a real `asset_tag` column. The other item types have no tag
 * column, so their tag is derived from the id behind a fixed prefix. This class
 * is the single source of truth for that mapping: the label printer formats
 * tags with it, and the global search resolves scanned tags with it, so the two
 * can never drift apart.
 */
class ItemTag
{
    /**
     * Prefix => model class, for types whose tag is derived from the id.
     */
    public const PREFIXES = [
        'AC' => Accessory::class,
        'CM' => Component::class,
        'CS' => Consumable::class,
        'BX' => Location::class,
    ];

    /**
     * The tag to print on this item's label.
     */
    public static function for(object $item): string
    {
        if ($item instanceof Asset) {
            return (string) $item->asset_tag;
        }

        foreach (self::PREFIXES as $prefix => $class) {
            if ($item instanceof $class) {
                return $prefix.'-'.$item->id;
            }
        }

        return '';
    }

    /**
     * Resolve a scanned/typed tag to the item it names.
     *
     * A term may be ambiguous: `AC-12` is accessory #12, but it could also be a
     * literal asset tag if this install's asset prefix happens to be "AC-". Both
     * candidates are therefore returned and the caller unions the results.
     *
     * @return array<int, object> the matched models (may be empty)
     */
    public static function resolve(string $term): array
    {
        $term = trim($term);

        if (! preg_match('/^([A-Za-z]{1,10})-(\d{1,10})$/', $term, $matches)) {
            return [];
        }

        [, $prefix, $digits] = $matches;
        $found = [];

        // Derived tags: AC-/CM-/CS-/BX- plus the record id.
        $class = self::PREFIXES[strtoupper($prefix)] ?? null;
        if ($class && $model = $class::find((int) $digits)) {
            $found[] = $model;
        }

        // Asset tags are stored, but may be zero-padded ("SW-134" -> "SW-0000000134").
        if ($asset = Asset::whereIn('asset_tag', self::assetTagCandidates($prefix, $digits))->first()) {
            $found[] = $asset;
        }

        return $found;
    }

    /**
     * The asset_tag spellings a "<prefix>-<digits>" term could refer to: the
     * term as typed, and the zero-padded form the auto-increment tags use.
     *
     * @return array<int, string>
     */
    public static function assetTagCandidates(string $prefix, string $digits): array
    {
        $candidates = [$prefix.'-'.$digits];

        $zerofill = (int) (Setting::getSettings()->zerofill_count ?? 0);

        if ($zerofill > 0) {
            $candidates[] = $prefix.'-'.Asset::zerofill($digits, $zerofill);
        }

        return array_values(array_unique($candidates));
    }
}
