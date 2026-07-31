<?php

namespace Tests\Feature\NetworkLabelPrinter;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrintNetworkLabelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sw-label-printer.printers' => [
                'office' => 'http://printserver.test:9100',
                'lab' => 'http://lab.test:9100',
            ],
            'sw-label-printer.location_mapping' => ['-1' => 'office'],
        ]);

        // A fake URL pattern that fails to match would otherwise let the request
        // hit the real network; make that an immediate, obvious failure.
        Http::preventStrayRequests();
    }

    /**
     * Decode the `data` payload the print server received.
     */
    private function decodedPayload(Request $request): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return base64_decode($query['data']);
    }

    public function testAssetLabelIsSentToThePrintServer()
    {
        Http::fake(['*printserver.test*' => Http::response('', 200)]);

        $asset = Asset::factory()->create(['name' => 'Roadshow laptop', 'location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('hardware.show', $asset))
            ->get(route('network-label.asset', $asset))
            ->assertRedirect(route('hardware.show', $asset))
            ->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($asset) {
            $payload = $this->decodedPayload($request);

            return str_starts_with($request->url(), 'http://printserver.test:9100/print?')
                && $request->method() === 'POST'
                && str_starts_with($payload, $asset->asset_tag.'|Roadshow laptop|');
        });
    }

    public function testAccessoryComponentAndConsumableLabelsUsePrefixedTags()
    {
        Http::fake(['*printserver.test*' => Http::response('', 200)]);

        $user = User::factory()->superuser()->create();

        $accessory = Accessory::factory()->create(['name' => 'Dock', 'location_id' => null]);
        $component = Component::factory()->create(['name' => 'RAM', 'location_id' => null]);
        $consumable = Consumable::factory()->create(['name' => 'Toner', 'location_id' => null]);

        $this->actingAs($user)->get(route('network-label.accessory', $accessory))->assertSessionHas('success');
        $this->actingAs($user)->get(route('network-label.component', $component))->assertSessionHas('success');
        $this->actingAs($user)->get(route('network-label.consumable', $consumable))->assertSessionHas('success');

        foreach ([
            'AC-'.$accessory->id.'|Dock|',
            'CM-'.$component->id.'|RAM|',
            'CS-'.$consumable->id.'|Toner|',
        ] as $expected) {
            Http::assertSent(fn (Request $request) => str_starts_with($this->decodedPayload($request), $expected));
        }
    }

    public function testLocationLabelCarriesTheParentNameAsSubtitle()
    {
        Http::fake(['*printserver.test*' => Http::response('', 200)]);

        $parent = Location::factory()->create(['name' => 'Berlin']);
        $child = Location::factory()->create(['name' => 'Room 5', 'parent_id' => $parent->id]);

        config(['sw-label-printer.location_mapping' => [(string) $parent->id => 'office']]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.location', $child))
            ->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($child) {
            return $this->decodedPayload($request) === 'BX-'.$child->id.'|Room 5|Berlin';
        });
    }

    public function testLabelIsRoutedByTheItemsTopLevelLocation()
    {
        Http::fake(['*lab.test*' => Http::response('', 200)]);

        $root = Location::factory()->create();
        $leaf = Location::factory()->create(['parent_id' => $root->id]);
        $asset = Asset::factory()->create(['location_id' => $leaf->id, 'rtd_location_id' => $leaf->id]);

        config(['sw-label-printer.location_mapping' => [(string) $root->id => 'lab']]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset))
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://lab.test:9100/print?'));
    }

    public function testExplicitPrinterParameterSelectsThatPrinter()
    {
        Http::fake(['*lab.test*' => Http::response('', 200)]);

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset).'?printer=lab')
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://lab.test:9100/print?'));
    }

    public function testUnknownPrinterParameterNeverContactsAnyHost()
    {
        Http::fake();

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset).'?printer=http://evil.example.com')
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function testUnmappedLocationDoesNotPrintAtTheDefaultPrinter()
    {
        Http::fake();

        $location = Location::factory()->create();
        $asset = Asset::factory()->create(['location_id' => $location->id, 'rtd_location_id' => $location->id]);

        // '-1' => office is configured, but it only applies to items with no location.
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset))
            ->assertSessionHas('error', trans('label-printer.no_printer'));

        Http::assertNothingSent();
    }

    public function testPrintServerErrorsAreReportedToTheUser()
    {
        Http::fake(['*printserver.test*' => Http::response('', 403)]);

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset))
            ->assertSessionHas('error', trans('label-printer.denied', ['printer' => 'office']));
    }

    public function testUnexpectedStatusCodesAreReportedWithTheCode()
    {
        Http::fake(['*printserver.test*' => Http::response('', 500)]);

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset))
            ->assertSessionHas('error', trans('label-printer.failed', ['printer' => 'office', 'code' => 500]));
    }

    public function testUnreachablePrintServerIsReportedAndDoesNotThrow()
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('network-label.asset', $asset))
            ->assertSessionHas('error', trans('label-printer.unreachable', ['printer' => 'office']));
    }

    public function testUsersWithoutViewPermissionCannotPrint()
    {
        Http::fake();

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('network-label.asset', $asset))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function testPrintActionIsRenderedOnTheAssetViewPage()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk()
            ->assertSee(route('network-label.asset', $asset))
            // Two printers are configured, so the picker is offered.
            ->assertSee(trans('label-printer.choose_printer'));
    }

    public function testPrintActionIsHiddenWhenNoPrinterIsConfigured()
    {
        config(['sw-label-printer.printers' => [], 'sw-label-printer.location_mapping' => []]);

        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk()
            ->assertDontSee(route('network-label.asset', $asset));
    }

    public function testGuestsAreRedirectedToLogin()
    {
        Http::fake();

        $asset = Asset::factory()->create(['location_id' => null]);

        $this->get(route('network-label.asset', $asset))->assertRedirect(route('login'));

        Http::assertNothingSent();
    }
}
