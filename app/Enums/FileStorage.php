<?php

namespace App\Enums;

/**
 * Single source of truth for file-storage directory names across the app.
 *
 * Each case backs a directory subdir name. Scope methods declare
 * whether the directory lives under public, private, or both. Path /
 * directory accessors derive from the backing value plus the scope.
 *
 * Call sites use the enum cases directly rather than passing literal
 * strings, so IDE autocomplete / static analysis will catch typos:
 *
 *     FileStorage::Signatures->privatePath();
 *     FileStorage::Avatars->publicPath();
 *     FileStorage::publicDirs(); // for enumeration
 *     FileStorage::privateDirs();
 *
 * Models with image/avatar fields use the `HasImageUpload`
 * trait to declare their resource once and resolve the path via
 * `$model->imageUploadPath()`.
 */
enum FileStorage: string
{
    case Accessories = 'accessories';
    case Assets = 'assets';
    case Audits = 'audits';
    case Avatars = 'avatars';
    case Barcodes = 'barcodes';
    case Categories = 'categories';
    case Companies = 'companies';
    case Components = 'components';
    case Consumables = 'consumables';
    case Departments = 'departments';
    case EulaPdfs = 'eula-pdfs';
    case Imports = 'imports';
    case Licenses = 'licenses';
    case Locations = 'locations';
    case Maintenances = 'maintenances';
    case Manufacturers = 'manufacturers';
    case Models = 'models';
    case Signatures = 'signatures';
    case Suppliers = 'suppliers';
    case Users = 'users';

    /**
     * Historical private-tree directory renames. Backups from older
     * installs may carry the old subdir name. The restore maps old to
     * new so the files end up in the current directory.
     *
     * @var array<string, string>
     */
    private const PRIVATE_ALIASES = [
        'assetmodels' => 'models',
        'asset_maintenances' => 'maintenances',
    ];

    public function hasPublicScope(): bool
    {
        return match ($this) {
            self::Accessories,
            self::Assets,
            self::Avatars,
            self::Barcodes,
            self::Categories,
            self::Companies,
            self::Components,
            self::Consumables,
            self::Departments,
            self::Locations,
            self::Maintenances,
            self::Manufacturers,
            self::Models,
            self::Suppliers => true,
            default => false,
        };
    }

    public function hasPrivateScope(): bool
    {
        return match ($this) {
            self::Accessories,
            self::Assets,
            self::Audits,
            self::Components,
            self::Consumables,
            self::EulaPdfs,
            self::Imports,
            self::Licenses,
            self::Locations,
            self::Maintenances,
            self::Models,
            self::Signatures,
            self::Users => true,
            default => false,
        };
    }

    /**
     * Public upload subdir with trailing slash, suitable for appending
     * a filename. Returns null when the resource has no public scope.
     */
    public function publicPath(): ?string
    {
        return $this->hasPublicScope() ? $this->value.'/' : null;
    }

    /**
     * Private upload subdir with trailing slash. Returns null when the
     * resource has no private scope.
     */
    public function privatePath(): ?string
    {
        return $this->hasPrivateScope() ? $this->value.'/' : null;
    }

    /**
     * Full public directory, relative to the repo root. For restore
     * extraction + pruning.
     */
    public function publicDir(): ?string
    {
        return $this->hasPublicScope() ? 'public/uploads/'.$this->value : null;
    }

    /**
     * Full private directory, relative to the repo root. For restore
     * extraction + pruning.
     */
    public function privateDir(): ?string
    {
        return $this->hasPrivateScope() ? 'storage/private_uploads/'.$this->value : null;
    }

    /**
     * All public directories as a flat list. Format matches what
     * RestoreFromBackup's extraction + pruning loops consume.
     *
     * @return list<string>
     */
    public static function publicDirs(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            if ($case->hasPublicScope()) {
                $out[] = $case->publicDir();
            }
        }

        return $out;
    }

    /**
     * All private directories, including historical aliases mapped to
     * their current destinations.
     * Integer keys for straight entries, string keys for alias =>
     * destination.
     *
     * @return array<int|string, string>
     */
    public static function privateDirs(): array
    {
        $out = [];
        $privateNames = [];
        foreach (self::cases() as $case) {
            if ($case->hasPrivateScope()) {
                $out[] = $case->privateDir();
                $privateNames[$case->value] = true;
            }
        }
        foreach (self::PRIVATE_ALIASES as $old => $new) {
            if (isset($privateNames[$new])) {
                $out['storage/private_uploads/'.$old] = 'storage/private_uploads/'.$new;
            }
        }

        return $out;
    }
}
