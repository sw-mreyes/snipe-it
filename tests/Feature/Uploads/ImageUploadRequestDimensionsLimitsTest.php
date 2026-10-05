<?php

namespace Tests\Feature\Uploads;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ImageUploadRequest's rules() previously only applied `mimes:`, so an
 * authenticated user could submit a PNG with large declared dimensions
 * but a tiny on-disk footprint. Image::make() fully decoded the raster
 * (width * height * 4 bytes for RGBA) before resize() ran, which is the
 * attack surface for the compressed-image-to-huge-pixel-buffer amplification.
 *
 * The fix adds dimensions:max_width/max_height, which runs via
 * getimagesize() - header-read only, so the validator itself does not
 * allocate the pixel buffer. SVGs are hard-skipped by Laravel's dimensions
 * validator and continue to flow through the sanitizer path.
 */
class ImageUploadRequestDimensionsLimitsTest extends TestCase
{
    public function test_avatar_with_oversized_declared_dimensions_is_rejected_before_decode(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);

        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $this->craftPngWithIhdrDimensions(50000, 50000));
        $upload = new UploadedFile($path, 'bomb.png', 'image/png', null, true);

        $response = $this->actingAs($user)
            ->post(route('profile.update'), [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $upload,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('avatar');

        $user->refresh();
        $this->assertNull(
            $user->avatar,
            'A rejected avatar upload must not touch the stored avatar reference.',
        );
    }

    public function test_favicon_dimensions_are_capped_tighter_than_image_and_avatar(): void
    {
        Storage::fake('public');

        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $this->craftPngWithIhdrDimensions(2048, 2048));
        $upload = new UploadedFile($path, 'big-favicon.png', 'image/png', null, true);

        $request = new \App\Http\Requests\ImageUploadRequest;
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['favicon' => $upload, 'image' => $upload, 'avatar' => $upload],
            $request->rules(),
        );

        $errors = $validator->errors();
        $this->assertTrue($errors->has('favicon'), 'A 2048x2048 image must fail the favicon dimensions cap (1024).');
        $this->assertFalse($errors->has('image'), 'A 2048x2048 image must pass the image dimensions cap (10000).');
        $this->assertFalse($errors->has('avatar'), 'A 2048x2048 image must pass the avatar dimensions cap (10000).');
    }

    public function test_normal_sized_avatar_still_passes_validation(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => UploadedFile::fake()->image('normal.png', 400, 400),
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull(
            $user->avatar,
            'A normal-sized avatar must still be accepted and stored.',
        );
    }

    public function test_manufacturer_image_with_oversized_declared_dimensions_is_rejected(): void
    {
        Storage::fake('public');

        $manufacturer = Manufacturer::factory()->create(['image' => null]);

        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $this->craftPngWithIhdrDimensions(50000, 50000));
        $upload = new UploadedFile($path, 'bomb.png', 'image/png', null, true);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('manufacturers.update', $manufacturer), [
                'name' => $manufacturer->name,
                'image' => $upload,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('image');

        $manufacturer->refresh();
        $this->assertNull(
            $manufacturer->image,
            'A rejected image upload must not touch the stored image reference.',
        );
    }

    /**
     * Produces a minimal PNG that declares the given dimensions in its IHDR
     * chunk. getimagesize() reads the header and reports those dimensions
     * without decoding any raster data, so Laravel's dimensions validator
     * rejects the file before any image decoder runs. The file itself is
     * tens of bytes - no pixel buffer is ever allocated in the test.
     */
    private function craftPngWithIhdrDimensions(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";

        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdrChunk = pack('N', 13).'IHDR'.$ihdrData.pack('N', crc32('IHDR'.$ihdrData));

        $iendChunk = pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        return $signature.$ihdrChunk.$iendChunk;
    }
}
