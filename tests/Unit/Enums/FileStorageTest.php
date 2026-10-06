<?php

namespace Tests\Unit\Enums;

use App\Enums\FileStorage;
use Tests\TestCase;

class FileStorageTest extends TestCase
{
    public function test_public_only_cases_expose_public_paths_and_not_private(): void
    {
        $this->assertSame('avatars/', FileStorage::Avatars->publicPath());
        $this->assertSame('public/uploads/avatars', FileStorage::Avatars->publicDir());
        $this->assertNull(FileStorage::Avatars->privatePath());
        $this->assertNull(FileStorage::Avatars->privateDir());
    }

    public function test_private_only_cases_expose_private_paths_and_not_public(): void
    {
        $this->assertSame('signatures/', FileStorage::Signatures->privatePath());
        $this->assertSame('storage/private_uploads/signatures', FileStorage::Signatures->privateDir());
        $this->assertNull(FileStorage::Signatures->publicPath());
        $this->assertNull(FileStorage::Signatures->publicDir());
    }

    public function test_both_scope_cases_expose_both_paths(): void
    {
        // Assets split content across both scopes: public holds asset
        // pictures, private holds asset file attachments.
        $this->assertSame('assets/', FileStorage::Assets->publicPath());
        $this->assertSame('assets/', FileStorage::Assets->privatePath());
        $this->assertSame('public/uploads/assets', FileStorage::Assets->publicDir());
        $this->assertSame('storage/private_uploads/assets', FileStorage::Assets->privateDir());
    }

    public function test_public_dirs_enumeration_includes_barcodes_cache(): void
    {
        // Barcode cache dir has to be in the public enumeration so the
        // restore prunes stale QR / 1D barcode images that would
        // otherwise linger from the previous install.
        $this->assertContains('public/uploads/barcodes', FileStorage::publicDirs());
    }

    public function test_private_dirs_enumeration_includes_historical_aliases(): void
    {
        // Backups from older installs may carry old subdir names.
        // The restore maps them to their current destinations so the
        // files land correctly after the rename.
        $privateDirs = FileStorage::privateDirs();

        $this->assertArrayHasKey('storage/private_uploads/assetmodels', $privateDirs);
        $this->assertSame('storage/private_uploads/models', $privateDirs['storage/private_uploads/assetmodels']);

        $this->assertArrayHasKey('storage/private_uploads/asset_maintenances', $privateDirs);
        $this->assertSame('storage/private_uploads/maintenances', $privateDirs['storage/private_uploads/asset_maintenances']);
    }

    public function test_public_dirs_includes_every_public_scoped_case(): void
    {
        $publicDirs = FileStorage::publicDirs();
        foreach (FileStorage::cases() as $case) {
            if ($case->hasPublicScope()) {
                $this->assertContains($case->publicDir(), $publicDirs, "Public dir enumeration missing {$case->value}.");
            }
        }
    }

    public function test_private_dirs_includes_every_private_scoped_case(): void
    {
        $privateDirs = FileStorage::privateDirs();
        foreach (FileStorage::cases() as $case) {
            if ($case->hasPrivateScope()) {
                $this->assertContains($case->privateDir(), $privateDirs, "Private dir enumeration missing {$case->value}.");
            }
        }
    }
}
