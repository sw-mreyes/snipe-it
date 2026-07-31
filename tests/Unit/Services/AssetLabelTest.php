<?php

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Services\AssetLabel;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Pure string logic, so this boots the framework without the database layer
 * (see PrinterResolverTest for why Tests\TestCase is avoided here).
 */
class AssetLabelTest extends TestCase
{
    use CreatesApplication;

    private function asset(?string $name, ?string $tag, int $id = 42): Asset
    {
        $asset = new Asset(['name' => $name, 'asset_tag' => $tag]);
        $asset->id = $id;

        return $asset;
    }

    public function testTheNameIsPreferred()
    {
        $this->assertSame('Roadshow laptop', AssetLabel::for($this->asset('Roadshow laptop', 'SW-000123')));
    }

    public function testFallsBackToTheAssetTagWhenThereIsNoName()
    {
        $this->assertSame('SW-000123', AssetLabel::for($this->asset(null, 'SW-000123')));
    }

    public function testFallsBackToTheAssetTagWhenTheNameIsBlank()
    {
        $this->assertSame('SW-000123', AssetLabel::for($this->asset('', 'SW-000123')));
    }

    public function testFallsBackToTheAssetTagWhenTheNameIsOnlyWhitespace()
    {
        $this->assertSame('SW-000123', AssetLabel::for($this->asset('   ', 'SW-000123')));
    }

    public function testFallsBackToTheRecordIdWhenNameAndTagAreBothEmpty()
    {
        $this->assertSame('#42', AssetLabel::for($this->asset('', '', 42)));
    }

    public function testANumericAssetTagIsStillUsable()
    {
        // Seeded installs have bare numeric tags; they are a valid fallback.
        $this->assertSame('223179954', AssetLabel::for($this->asset(null, '223179954')));
    }

    public function testSurroundingWhitespaceIsTrimmed()
    {
        $this->assertSame('Roadshow laptop', AssetLabel::for($this->asset('  Roadshow laptop  ', 'SW-1')));
    }
}
