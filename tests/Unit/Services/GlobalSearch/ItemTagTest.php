<?php

namespace Tests\Unit\Services\GlobalSearch;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use App\Services\GlobalSearch\ItemTag;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Formatting a tag needs no database, so this boots the framework without the
 * DB layer (see PrinterResolverTest for why Tests\TestCase is avoided here).
 *
 * The reverse direction — resolving a tag back to its item — does hit the
 * database and is covered by Tests\Feature\Search\GlobalSearchTest.
 */
class ItemTagTest extends TestCase
{
    use CreatesApplication;

    public function testAssetTagIsTheStoredAssetTag()
    {
        $asset = new Asset(['asset_tag' => 'SW-000123']);

        $this->assertSame('SW-000123', ItemTag::for($asset));
    }

    public function testDerivedTypesArePrefixedWithTheirId()
    {
        $accessory = new Accessory;
        $accessory->id = 12;

        $component = new Component;
        $component->id = 3;

        $consumable = new Consumable;
        $consumable->id = 7;

        $location = new Location;
        $location->id = 5;

        $this->assertSame('AC-12', ItemTag::for($accessory));
        $this->assertSame('CM-3', ItemTag::for($component));
        $this->assertSame('CS-7', ItemTag::for($consumable));
        $this->assertSame('BX-5', ItemTag::for($location));
    }

    public function testUntaggedTypesYieldAnEmptyTag()
    {
        $user = new User;
        $user->id = 1;

        $this->assertSame('', ItemTag::for($user));
    }

    public function testNonTagTermsAreNotParsed()
    {
        // Plain words, and prefixes with no number, are never tags.
        $this->assertSame([], ItemTag::resolve('ThinkPad'));
        $this->assertSame([], ItemTag::resolve('AC-'));
        $this->assertSame([], ItemTag::resolve(''));
    }

    public function testEveryPrefixMapsToADistinctModel()
    {
        // Guards against a copy-paste slip in the prefix table, which would
        // silently point scanned labels at the wrong entity type.
        $this->assertSame(
            count(ItemTag::PREFIXES),
            count(array_unique(ItemTag::PREFIXES))
        );
    }
}
