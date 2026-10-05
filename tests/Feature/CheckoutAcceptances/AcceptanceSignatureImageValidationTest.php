<?php

namespace Tests\Feature\CheckoutAcceptances;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcceptanceSignatureImageValidationTest extends TestCase
{
    private function pendingAcceptance(User $target): CheckoutAcceptance
    {
        $asset = Asset::factory()->assignedToUser($target)->create();

        return CheckoutAcceptance::factory()->pending()->for($asset, 'checkoutable')->create([
            'assigned_to_id' => $target->id,
        ]);
    }

    private function enableRequiredSignatures(): void
    {
        $settings = Setting::query()->firstOrFail();
        $settings->require_accept_signature = 1;
        $settings->save();
        Setting::$_cache = null;
    }

    private function tinyPng(int $width = 2, int $height = 2): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($canvas);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    /**
     * Craft a PNG whose IHDR declares the given dimensions. Used to
     * exercise the dimension cap without actually allocating a huge
     * pixel buffer in the test itself. getimagesizefromstring reads
     * the IHDR header and reports these declared dimensions, so the
     * cap triggers before any decoder opens the raster.
     */
    private function pngWithIhdrDimensions(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdrChunk = pack('N', 13) . 'IHDR' . $ihdrData . pack('N', crc32('IHDR' . $ihdrData));
        $iendChunk = pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));

        return $signature . $ihdrChunk . $iendChunk;
    }

    public function test_signature_output_with_non_image_bytes_is_rejected_and_nothing_is_written(): void
    {
        Storage::fake();
        $this->enableRequiredSignatures();

        $target = User::factory()->create();
        $acceptance = $this->pendingAcceptance($target);

        $marker = 'SNIPE-CAND-11-NOT-A-PNG';
        $payload = [
            'asset_acceptance' => 'accepted',
            'signature_output' => 'data:image/png;base64,' . base64_encode($marker),
        ];

        $this->actingAs($target)
            ->post(route('account.store-acceptance', $acceptance), $payload)
            ->assertSessionHas('error');

        $acceptance->refresh();
        $this->assertNull($acceptance->accepted_at, 'A rejected signature must not finalize the acceptance.');

        $written = Storage::disk()->files('private_uploads/signatures');
        $this->assertEmpty($written, 'A rejected signature must not land any file under signatures/.');
    }

    public function test_signature_output_with_oversized_declared_dimensions_is_rejected_before_decode(): void
    {
        Storage::fake();
        $this->enableRequiredSignatures();

        $target = User::factory()->create();
        $acceptance = $this->pendingAcceptance($target);

        // A PNG that declares 10000x10000 in its IHDR but is only a few
        // dozen bytes on the wire. getimagesizefromstring reports the
        // declared dimensions, the cap trips, and the request is
        // rejected without imagecreatefromstring ever running.
        $bombHeader = $this->pngWithIhdrDimensions(10000, 10000);
        $payload = [
            'asset_acceptance' => 'accepted',
            'signature_output' => 'data:image/png;base64,' . base64_encode($bombHeader),
        ];

        $this->actingAs($target)
            ->post(route('account.store-acceptance', $acceptance), $payload)
            ->assertSessionHas('error');

        $acceptance->refresh();
        $this->assertNull($acceptance->accepted_at);
        $this->assertEmpty(Storage::disk()->files('private_uploads/signatures'));
    }

    public function test_signature_output_exceeding_2mb_cap_is_rejected_by_validator(): void
    {
        Storage::fake();
        $this->enableRequiredSignatures();

        $target = User::factory()->create();
        $acceptance = $this->pendingAcceptance($target);

        $payload = [
            'asset_acceptance' => 'accepted',
            'signature_output' => 'data:image/png;base64,' . str_repeat('A', 2_100_000),
        ];

        $this->actingAs($target)
            ->post(route('account.store-acceptance', $acceptance), $payload)
            ->assertSessionHasErrors('signature_output');

        $acceptance->refresh();
        $this->assertNull($acceptance->accepted_at);
    }

    public function test_legitimate_tiny_png_signature_still_finalizes_acceptance(): void
    {
        // Companion: the fix must not break the happy path. A real tiny
        // PNG from the signaturepad canvas should continue to validate,
        // flatten, store, and finalize.
        Storage::fake();
        $this->enableRequiredSignatures();

        $target = User::factory()->create();
        $acceptance = $this->pendingAcceptance($target);

        $payload = [
            'asset_acceptance' => 'accepted',
            'signature_output' => 'data:image/png;base64,' . base64_encode($this->tinyPng()),
        ];

        $this->actingAs($target)
            ->post(route('account.store-acceptance', $acceptance), $payload)
            ->assertRedirectToRoute('account.accept')
            ->assertSessionHas('success');

        $acceptance->refresh();
        $this->assertNotNull($acceptance->accepted_at, 'A legitimate PNG signature must still finalize the acceptance.');
        $this->assertCount(1, Storage::disk()->files('private_uploads/signatures'));
    }
}
