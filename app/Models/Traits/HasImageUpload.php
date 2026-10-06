<?php

namespace App\Models\Traits;

use App\Enums\FileStorage;
use LogicException;

/**
 * Models with an `image` / `avatar` / `logo` column mix this in to
 * resolve their public upload directory via the FileStorage enum,
 * so call sites never pass raw string keys around.
 *
 * Single-image models declare their one storage once:
 *
 *     class User extends SnipeModel
 *     {
 *         use HasImageUpload;
 *
 *         public static function fileStorage(string $field = 'image'): FileStorage
 *         {
 *             return FileStorage::Avatars;
 *         }
 *     }
 *
 *     $user->imageUploadPath();   // 'avatars/'
 *     $asset->imageUploadPath();  // 'assets/'
 *
 * Multi-image models (Settings has logo, email_logo, favicon,
 * default_avatar, etc.) switch on the field name:
 *
 *     public static function fileStorage(string $field = 'image'): FileStorage
 *     {
 *         return match ($field) {
 *             'default_avatar' => FileStorage::Avatars,
 *             default => throw new LogicException("No storage mapping for {$field}"),
 *         };
 *     }
 *
 * This is separate from `HasUploads`, which handles the Files-tab
 * attachment relationship (not model-owned image fields).
 */
trait HasImageUpload
{
    /**
     * Return the FileStorage case for this model's image upload at
     * the given field. Defaults to the `image` field, which covers
     * the common single-image case. Multi-image models switch on
     * `$field` to resolve the right case per field.
     *
     * Must be in the public scope (`hasPublicScope() === true`).
     */
    abstract public static function fileStorage(string $field = 'image'): FileStorage;

    /**
     * Public upload directory for a specific image field on this
     * model, with trailing slash, suitable for appending a filename.
     * Defaults to the `image` field.
     */
    public function imageUploadPath(string $field = 'image'): string
    {
        $resource = static::fileStorage($field);
        $path = $resource->publicPath();
        if ($path === null) {
            throw new LogicException(
                static::class.' uses HasImageUpload but '.$resource->value.' has no public scope for field '.$field
            );
        }

        return $path;
    }
}
