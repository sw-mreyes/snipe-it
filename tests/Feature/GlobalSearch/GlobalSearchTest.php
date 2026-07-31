<?php

namespace Tests\Feature\GlobalSearch;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    /**
     * @return array<int, array{type: string, id: int, name: string}>
     */
    private function search(User $user, string $term): array
    {
        return $this->actingAsForApi($user)
            ->getJson(route('api.search.index', ['q' => $term]))
            ->assertOk()
            ->json('rows');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function keys(array $rows): array
    {
        return array_map(fn ($row) => $row['type'].':'.$row['id'], $rows);
    }

    public function testFindsItemsByName()
    {
        $user = User::factory()->superuser()->create();
        $asset = Asset::factory()->create(['name' => 'Roadshow ThinkPad']);

        $this->assertContains('asset:'.$asset->id, $this->keys($this->search($user, 'Roadshow')));
    }

    public function testCategoryMatchReturnsTheCategoryAndItsMembers()
    {
        $user = User::factory()->superuser()->create();

        $category = Category::factory()->forAccessories()->create(['name' => 'Zetta Widgets']);
        $accessory = Accessory::factory()->create(['category_id' => $category->id]);

        $keys = $this->keys($this->search($user, 'Zetta Widgets'));

        // Decision S1: the container row *and* what is inside it.
        $this->assertContains('category:'.$category->id, $keys);
        $this->assertContains('accessory:'.$accessory->id, $keys);
    }

    public function testAssetModelMatchReturnsTheModelAndItsAssets()
    {
        $user = User::factory()->superuser()->create();

        $model = AssetModel::factory()->create(['name' => 'Zetta Laptop 9000']);
        $asset = Asset::factory()->create(['model_id' => $model->id]);

        $keys = $this->keys($this->search($user, 'Zetta Laptop 9000'));

        $this->assertContains('assetModel:'.$model->id, $keys);
        $this->assertContains('asset:'.$asset->id, $keys);
    }

    public function testLocationMatchReturnsTheLocationAndWhatIsStoredThere()
    {
        $user = User::factory()->superuser()->create();

        $location = Location::factory()->create(['name' => 'Zetta Warehouse']);
        $asset = Asset::factory()->create(['location_id' => $location->id, 'rtd_location_id' => $location->id]);
        $accessory = Accessory::factory()->create(['location_id' => $location->id]);

        $keys = $this->keys($this->search($user, 'Zetta Warehouse'));

        // Decision S3: the location row *and* its contents.
        $this->assertContains('location:'.$location->id, $keys);
        $this->assertContains('asset:'.$asset->id, $keys);
        $this->assertContains('accessory:'.$accessory->id, $keys);
    }

    public function testResolvesPrintedTagsForNonAssetTypes()
    {
        $user = User::factory()->superuser()->create();

        $accessory = Accessory::factory()->create();
        $component = Component::factory()->create();
        $consumable = Consumable::factory()->create();

        // Decision S2: the tags the label printer emits must resolve.
        $this->assertContains('accessory:'.$accessory->id, $this->keys($this->search($user, 'AC-'.$accessory->id)));
        $this->assertContains('component:'.$component->id, $this->keys($this->search($user, 'CM-'.$component->id)));
        $this->assertContains('consumable:'.$consumable->id, $this->keys($this->search($user, 'CS-'.$consumable->id)));
    }

    public function testResolvesALocationTagAndListsItsContents()
    {
        $user = User::factory()->superuser()->create();

        $location = Location::factory()->create();
        $asset = Asset::factory()->create(['location_id' => $location->id, 'rtd_location_id' => $location->id]);

        $keys = $this->keys($this->search($user, 'BX-'.$location->id));

        $this->assertContains('location:'.$location->id, $keys);
        $this->assertContains('asset:'.$asset->id, $keys);
    }

    public function testResolvesAnAssetTagExactly()
    {
        $user = User::factory()->superuser()->create();
        $asset = Asset::factory()->create(['asset_tag' => 'SW-000123']);

        $this->assertContains('asset:'.$asset->id, $this->keys($this->search($user, 'SW-000123')));
    }

    public function testResolvesAnUnpaddedAssetTagUsingZerofill()
    {
        $this->settings->set(['zerofill_count' => 6]);

        $user = User::factory()->superuser()->create();
        $asset = Asset::factory()->create(['asset_tag' => 'SW-000123']);

        // "SW-123" is what a human types; the stored tag is zero-padded.
        $this->assertContains('asset:'.$asset->id, $this->keys($this->search($user, 'SW-123')));
    }

    public function testCommaSeparatedTermsAreUnioned()
    {
        $user = User::factory()->superuser()->create();

        $asset = Asset::factory()->create(['name' => 'Zetta Laptop']);
        $accessory = Accessory::factory()->create();

        // Decision S4: several terms, one result set.
        $keys = $this->keys($this->search($user, 'Zetta Laptop, AC-'.$accessory->id));

        $this->assertContains('asset:'.$asset->id, $keys);
        $this->assertContains('accessory:'.$accessory->id, $keys);
    }

    public function testResultsAreDeduplicated()
    {
        $user = User::factory()->superuser()->create();

        // Reachable by its own name AND through its location.
        $location = Location::factory()->create(['name' => 'Zetta Site']);
        $asset = Asset::factory()->create([
            'name' => 'Zetta Site spare',
            'location_id' => $location->id,
            'rtd_location_id' => $location->id,
        ]);

        $keys = $this->keys($this->search($user, 'Zetta Site'));

        $this->assertSame(1, count(array_keys($keys, 'asset:'.$asset->id)));
    }

    public function testRowsCarryLinksAndActions()
    {
        $user = User::factory()->superuser()->create();
        $asset = Asset::factory()->create(['name' => 'Zetta Linkable']);

        $rows = $this->search($user, 'Zetta Linkable');
        $row = collect($rows)->firstWhere('type', 'asset');

        $this->assertSame(route('hardware.show', $asset->id), $row['view_url']);
        $this->assertSame(route('hardware.checkout.create', $asset->id), $row['checkout_url']);
        $this->assertSame(route('network-label.asset', $asset->id), $row['print_url']);
        // The transformer stringifies; factory tags may be numeric.
        $this->assertSame((string) $asset->asset_tag, $row['tag']);
        $this->assertTrue($row['available_actions']['view']);
    }

    public function testOnlyReturnsTypesTheUserMaySee()
    {
        // Can view accessories, but not assets.
        $user = User::factory()->viewAccessories()->create();

        $asset = Asset::factory()->create(['name' => 'Zetta Restricted']);
        $accessory = Accessory::factory()->create(['name' => 'Zetta Restricted']);

        $keys = $this->keys($this->search($user, 'Zetta Restricted'));

        $this->assertContains('accessory:'.$accessory->id, $keys);
        $this->assertNotContains('asset:'.$asset->id, $keys);
    }

    public function testEmptyTermReturnsNothing()
    {
        $user = User::factory()->superuser()->create();
        Asset::factory()->create();

        $this->assertSame([], $this->search($user, ''));
    }

    public function testSearchPageRenders()
    {
        $user = User::factory()->superuser()->create();

        $this->actingAs($user)
            ->get(route('search', ['search' => 'Zetta']))
            ->assertOk()
            ->assertSee(route('api.search.index', ['q' => 'Zetta']), false);
    }

    public function testGuestsCannotSearch()
    {
        // A user has to exist, otherwise the install is considered un-set-up and
        // everything redirects to /setup before auth is ever consulted.
        User::factory()->create();

        $this->get(route('search'))->assertRedirect(route('login'));
    }
}
