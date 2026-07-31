<?php

namespace App\Services;

use App\Models\Asset;

/**
 * How an asset is named in lists that are about something else — reservation
 * tables, calendar entries, pickers (custom fork feature).
 *
 * Assets may legitimately have no name, and an asset tag may be a bare number,
 * so a single fallback is not enough. One class owns the rule so the table, the
 * calendar and the detail page can never disagree about what an asset is called.
 */
class AssetLabel
{
    /**
     * Preference order: the asset's name, then its (printed) asset tag, then
     * the record id as a last resort.
     */
    public static function for(Asset $asset): string
    {
        $name = trim((string) $asset->name);

        if ($name !== '') {
            return $name;
        }

        $tag = trim((string) $asset->asset_tag);

        if ($tag !== '') {
            return $tag;
        }

        return '#'.$asset->id;
    }
}
