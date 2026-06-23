<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Services\NetworkLabelPrinter\PrinterResolver;
use Tests\TestCase;

class PrinterResolverTest extends TestCase
{
    private function configurePrinters(array $printers, array $mapping): void
    {
        config([
            'sw-label-printer.printers' => $printers,
            'sw-label-printer.location_mapping' => $mapping,
        ]);
    }

    public function test_explicit_printer_param_must_match_a_configured_printer()
    {
        $this->configurePrinters(['floor1' => 'http://10.0.0.5:9100'], []);

        $resolver = new PrinterResolver;

        $this->assertSame(['http://10.0.0.5:9100', 'floor1'], $resolver->resolve(null, 'floor1'));
    }

    public function test_unknown_printer_param_is_rejected_and_never_used_as_a_host()
    {
        // Security: an attacker-supplied printer name that is not configured must
        // not resolve to anything (no arbitrary host / SSRF).
        $this->configurePrinters(['floor1' => 'http://10.0.0.5:9100'], ['-1' => 'floor1']);

        $resolver = new PrinterResolver;

        $this->assertNull($resolver->resolve(null, 'http://evil.example.com'));
        $this->assertNull($resolver->resolve(null, 'not-a-real-printer'));
    }

    public function test_falls_back_to_default_mapping_when_no_param_and_no_location()
    {
        $this->configurePrinters(['floor1' => 'http://10.0.0.5:9100'], ['-1' => 'floor1']);

        $resolver = new PrinterResolver;

        $this->assertSame(['http://10.0.0.5:9100', 'floor1'], $resolver->resolve(null));
    }

    public function test_returns_null_when_nothing_maps()
    {
        $this->configurePrinters(['floor1' => 'http://10.0.0.5:9100'], []);

        $resolver = new PrinterResolver;

        $this->assertNull($resolver->resolve(null));
    }

    public function test_maps_by_root_location()
    {
        $root = Location::factory()->create();
        $child = Location::factory()->create(['parent_id' => $root->id]);

        $this->configurePrinters(
            ['floorRoot' => 'http://10.0.0.9:9100'],
            [(string) $root->id => 'floorRoot'],
        );

        $resolver = new PrinterResolver;

        $this->assertSame(['http://10.0.0.9:9100', 'floorRoot'], $resolver->resolve($child));
    }

    public function test_mapped_printer_name_must_also_exist_in_printers()
    {
        // Mapping references a printer that isn't defined -> no resolution.
        $this->configurePrinters([], ['-1' => 'ghost']);

        $resolver = new PrinterResolver;

        $this->assertNull($resolver->resolve(null));
    }
}
