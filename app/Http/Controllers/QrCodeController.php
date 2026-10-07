<?php

namespace App\Http\Controllers;

use App\Enums\FileStorage;
use App\Helpers\Helper;
use App\Models\Setting;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QrCodeController extends Controller
{
    public static $map_show_route = [
        'accessories' => 'accessories.show',
        'assets' => 'hardware.show',
        'companies' => 'companies.show',
        'components' => 'components.show',
        'consumables' => 'consumables.show',
        'hardware' => 'hardware.show',
        'licenses' => 'licenses.show',
        'locations' => 'locations.show',
        'models' => 'models.show',
        'users' => 'users.show',
    ];

    public function show($object_type, $id): Response|BinaryFileResponse|string|bool
    {
        $settings = Setting::getSettings();

        if ($settings->label2_2d_type === 'none') {
            return false;
        }

        $unavailable = fn () => abort(404, trans('general.generic_model_not_found', ['model' => trans('general.item')]));

        if (! array_key_exists($object_type, self::$map_show_route)) {
            $unavailable();
        }

        $object = parent::getMapObjectType()[$object_type]::withTrashed()->find($id);

        if (! $object || ! Gate::allows('view', $object)) {
            $unavailable();
        }

        $size = Helper::barcodeDimensions($settings->label2_2d_type);
        $qr_key = FileStorage::Barcodes->publicPath().'qr-'.str_slug($object_type).'-'.str_slug($id).'.png';

        if (! Storage::disk('public')->exists($qr_key)) {
            $barcode = new Barcode;
            $barcode_obj = $barcode->getBarcodeObj(
                $settings->label2_2d_type,
                route(self::$map_show_route[$object_type], $id),
                $size['height'],
                $size['width'],
                'black',
                [-2, -2, -2, -2]
            );
            Storage::disk('public')->put($qr_key, $barcode_obj->getPngData());
        }

        return Storage::disk('public')->response($qr_key, basename($qr_key), ['Content-type' => 'image/png']);
    }
}
