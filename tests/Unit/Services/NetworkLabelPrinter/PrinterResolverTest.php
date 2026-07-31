<?php

namespace Tests\Unit\Services\NetworkLabelPrinter;

use App\Models\Location;
use App\Services\NetworkLabelPrinter\PrinterResolver;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * The resolver is pure config logic, so this deliberately does NOT extend
 * Tests\TestCase: that base class initializes settings from the database on
 * every setUp(), which triggers a full schema rebuild (~75s) even for tests
 * that never query anything. Booting the framework alone keeps config() and
 * the Location model available while staying database-free.
 *
 * Location graphs are therefore built in memory, with the `parent` relation
 * set explicitly so that touching it never falls through to a query.
 */
class PrinterResolverTest extends TestCase
{
    use CreatesApplication;

    private function configure(array $printers = [], array $mapping = []): void
    {
        config([
            'sw-label-printer.printers' => $printers,
            'sw-label-printer.location_mapping' => $mapping,
        ]);
    }

    /**
     * An in-memory location. Pass $parent to build a tree; the relation is
     * always set so the resolver never lazy-loads.
     */
    private function location(int $id, ?Location $parent = null): Location
    {
        $location = new Location(['name' => 'Location '.$id]);
        $location->id = $id;
        $location->setRelation('parent', $parent);

        return $location;
    }

    public function testReturnsPrinterNamesInConfigOrder()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100', 'lab' => 'http://10.0.0.6:9100']);

        $this->assertSame(['office', 'lab'], (new PrinterResolver)->printerNames());
    }

    public function testExplicitPrinterParameterSelectsThatPrinter()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100', 'lab' => 'http://10.0.0.6:9100']);

        $this->assertSame(
            ['http://10.0.0.6:9100', 'lab'],
            (new PrinterResolver)->resolve(null, 'lab')
        );
    }

    public function testUnknownPrinterParameterResolvesToNothing()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['-1' => 'office']);

        // Must not silently fall back to the location mapping either.
        $this->assertNull((new PrinterResolver)->resolve(null, 'nope'));
    }

    public function testPrinterParameterIsNeverTreatedAsAHost()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['-1' => 'office']);

        $this->assertNull((new PrinterResolver)->resolve(null, 'http://evil.example.com'));
        $this->assertNull((new PrinterResolver)->resolve(null, '10.0.0.99:9100'));
    }

    public function testItemWithoutLocationUsesTheMinusOneMapping()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['-1' => 'office']);

        $this->assertSame(
            ['http://10.0.0.5:9100', 'office'],
            (new PrinterResolver)->resolve(null)
        );
    }

    public function testItemWithoutLocationResolvesToNothingWhenNoDefaultMapped()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['5' => 'office']);

        $this->assertNull((new PrinterResolver)->resolve(null));
    }

    public function testMappedLocationResolvesToItsPrinter()
    {
        $this->configure(['lab' => 'http://10.0.0.6:9100'], ['7' => 'lab']);

        $this->assertSame(
            ['http://10.0.0.6:9100', 'lab'],
            (new PrinterResolver)->resolve($this->location(7))
        );
    }

    public function testNestedLocationResolvesThroughItsTopLevelParent()
    {
        $root = $this->location(1);
        $middle = $this->location(2, $root);
        $leaf = $this->location(3, $middle);

        $this->configure(['lab' => 'http://10.0.0.6:9100'], ['1' => 'lab']);

        $this->assertSame(
            ['http://10.0.0.6:9100', 'lab'],
            (new PrinterResolver)->resolve($leaf)
        );
    }

    public function testUnmappedLocationDoesNotFallBackToTheDefaultPrinter()
    {
        // A label for an unmapped site must not silently print at another site.
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['-1' => 'office']);

        $this->assertNull((new PrinterResolver)->resolve($this->location(9)));
    }

    public function testMappingToAnUnconfiguredPrinterResolvesToNothing()
    {
        $this->configure(['office' => 'http://10.0.0.5:9100'], ['9' => 'typo']);

        $this->assertNull((new PrinterResolver)->resolve($this->location(9)));
    }

    public function testCyclicParentChainDoesNotHang()
    {
        $a = $this->location(1);
        $b = $this->location(2, $a);
        $a->setRelation('parent', $b);

        $this->configure(['office' => 'http://10.0.0.5:9100'], ['1' => 'office', '2' => 'office']);

        $this->assertNotNull((new PrinterResolver)->resolve($a));
    }

    public function testEmptyConfigurationResolvesToNothing()
    {
        $this->configure();

        $this->assertSame([], (new PrinterResolver)->printerNames());
        $this->assertNull((new PrinterResolver)->resolve(null));
    }
}
